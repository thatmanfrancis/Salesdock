<?php

namespace Tests\Feature;

use App\Http\Middleware\ShareWorkspace;
use App\Models\BillingInvoice;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentManager;
use App\Services\RecurringBilling;
use App\Services\SaleRecorder;
use App\Services\SubscriptionPricing;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RecurringBillingTest extends TestCase
{
    private string $verificationStatus = 'success';

    private float $verificationAmount = 1000;

    private bool $verificationUnavailable = false;

    private bool $reusable = true;

    private string $channel = 'card';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-02 12:00:00', 'UTC'));
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'services.zeptomail.key' => '', 'services.zeptomail.from' => '']);
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $path): bool => ! str_contains($path, 'create_branch_stocks_table')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--no-interaction' => true])->assertExitCode(0);
    }

    #[TestWith(['paystack'])]
    #[TestWith(['flutterwave'])]
    public function test_only_explicit_card_consent_enables_encrypted_auto_renewal(string $provider): void
    {
        [$owner, $invoice] = $this->shop($provider);
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice, $provider);

        $this->assertTrue($subscription->autoRenew);
        $this->assertSame('payer@example.test', $subscription->recurringAuthorization['email']);
        $this->assertSame('4242', $subscription->recurringConsent['card']['last4']);
        $this->assertSame(RecurringBilling::TERMS_VERSION, $subscription->recurringConsent['version']);
        $this->assertStringNotContainsString('saved-token', $subscription->getRawOriginal('recurringAuthorization'));
        $this->assertArrayNotHasKey('recurringAuthorization', $subscription->toArray());
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
            && (($request['channels'] ?? []) === ['card'] || ($request['payment_options'] ?? '') === 'card'));
        $this->actingAs($owner)->get(route('billing.authorization', $invoice))
            ->assertOk()->assertSee('NGN 1,000.00')->assertSee('4242')->assertDontSee('saved-token');
    }

    public function test_manual_card_payment_does_not_enable_automatic_renewal(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice, 'paystack', false);

        $this->assertFalse($subscription->autoRenew);
        $this->assertNull($subscription->recurringAuthorization);
        $this->assertNull($invoice->fresh()->billingMetadata['consent']);
    }

    public function test_flutterwave_annual_checkout_requires_one_time_payment(): void
    {
        [$owner, $invoice] = $this->shop('flutterwave');
        $invoice->update(['billingCycle' => 'ANNUALLY']);
        Http::preventStrayRequests();

        $this->actingAs($owner)->postJson(route('billing.start', $invoice->invoiceNumber), ['autoRenew' => 1, 'authorizationVersion' => RecurringBilling::TERMS_VERSION])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');

        Http::assertNothingSent();
    }

    public function test_expired_card_pauses_renewal_before_submission(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);
        $subscription->update(['recurringAuthorization' => array_merge($subscription->recurringAuthorization, ['expires' => '11/26'])]);
        $this->travelTo($subscription->currentPeriodEnd->copy()->addMinute());

        app(RecurringBilling::class)->process($subscription);

        $this->assertFalse($subscription->fresh()->autoRenew);
        Http::assertNotSent(fn (ClientRequest $request): bool => $this->isCharge($request));
    }

    public function test_resuming_checkout_does_not_request_a_second_payment_or_change_consent(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $this->start($owner, $invoice);
        $this->start($owner, $invoice, false);

        $this->assertTrue($invoice->fresh()->billingMetadata['autoRenewRequested']);
        $this->assertCount(1, Http::recorded());
        $this->get(route('recurring.terms'))->assertOk()->assertSee('Cancellation');
        $this->get(route('terms'))->assertOk();
        $this->get(route('privacy'))->assertOk();
    }

    public function test_unchecked_auto_renewal_is_shown_with_an_exact_authorisation_and_date(): void
    {
        [$owner, $invoice] = $this->shop();
        Http::preventStrayRequests();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)->get(route('billing.pay', $invoice))
            ->assertOk()->assertSee('name="autoRenew" value="1"', false)->assertSee('NGN 1000.00')
            ->assertSee('02 Dec 2026')->assertSee('one-time card payment')->assertDontSee('name="autoRenew" value="1" checked', false);

        Http::assertNothingSent();
    }

    public function test_automatic_billing_requires_an_owner_and_current_terms_version(): void
    {
        [$owner, $invoice] = $this->shop();
        Http::preventStrayRequests();
        $this->postJson(route('billing.start', $invoice->invoiceNumber), ['autoRenew' => 1, 'authorizationVersion' => RecurringBilling::TERMS_VERSION])->assertForbidden();
        $this->actingAs($owner)->postJson(route('billing.start', $invoice->invoiceNumber), ['autoRenew' => 1, 'authorizationVersion' => 'outdated'])
            ->assertUnprocessable()->assertJsonValidationErrors('authorizationVersion');

        Http::assertNothingSent();
    }

    public function test_recurring_provider_enable_switch_is_required(): void
    {
        [$owner, $invoice] = $this->shop();
        PlatformSetting::writePayment(['provider' => 'paystack', 'platform_split' => 0, 'recurring_enabled' => false]);
        Http::preventStrayRequests();

        $this->actingAs($owner)->postJson(route('billing.start', $invoice->invoiceNumber), ['autoRenew' => 1, 'authorizationVersion' => RecurringBilling::TERMS_VERSION])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');

        Http::assertNothingSent();
    }

    public function test_non_reusable_card_does_not_prevent_paid_access_but_does_not_enable_renewal(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->reusable = false;
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);

        $this->assertSame('PAID', $invoice->fresh()->status);
        $this->assertSame('ACTIVE', $subscription->status);
        $this->assertFalse($subscription->autoRenew);
        $this->assertNull($subscription->recurringAuthorization);
    }

    #[TestWith(['paystack'])]
    #[TestWith(['flutterwave'])]
    public function test_renewal_is_submitted_once_and_extends_the_paid_period_once(string $provider): void
    {
        [$owner, $invoice] = $this->shop($provider);
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice, $provider);
        $due = $subscription->currentPeriodEnd->copy();
        $this->travelTo($due->copy()->addMinute());

        app(RecurringBilling::class)->process($subscription);
        app(RecurringBilling::class)->process($subscription);

        $this->assertSame($due->toDateTimeString(), $subscription->fresh()->currentPeriodStart->toDateTimeString());
        $this->assertSame('2027-01-02', $subscription->fresh()->currentPeriodEnd->toDateString());
        $this->assertSame(2, BillingInvoice::query()->where('status', 'PAID')->count());
        $this->assertSame(1, collect(Http::recorded())->filter(fn (array $record): bool => $this->isCharge($record[0]))->count());
        Http::assertSent(fn (ClientRequest $request): bool => $this->isCharge($request)
            && ($request['email'] ?? null) === 'payer@example.test'
            && ! isset($request['subaccount']) && ! isset($request['subaccounts']));
    }

    public function test_uncertain_charge_is_reconciled_without_a_second_charge(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);
        $this->travelTo($subscription->currentPeriodEnd->copy()->addMinute());
        $this->verificationUnavailable = true;

        app(RecurringBilling::class)->process($subscription);
        app(RecurringBilling::class)->process($subscription);
        $renewal = BillingInvoice::query()->where('invoiceNumber', 'like', 'REN-%')->firstOrFail();
        $this->assertSame('UNCERTAIN', $renewal->billingMetadata['chargeState']);
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)->post(route('billing.choose'), ['plan' => $subscription->planId, 'cycle' => 'MONTHLY'])
            ->assertSessionHasErrors('plan');
        $this->verificationUnavailable = false;
        app(RecurringBilling::class)->process($subscription);

        $this->assertSame('PAID', $renewal->fresh()->status);
        $this->assertSame(1, collect(Http::recorded())->filter(fn (array $record): bool => $this->isCharge($record[0]))->count());
    }

    public function test_decline_pauses_renewal_and_does_not_retry_the_card(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);
        $this->travelTo($subscription->currentPeriodEnd->copy()->addMinute());
        $this->verificationStatus = 'failed';

        app(RecurringBilling::class)->process($subscription);
        app(RecurringBilling::class)->process($subscription);

        $this->assertFalse($subscription->fresh()->autoRenew);
        $this->assertNull($subscription->fresh()->recurringAuthorization);
        $this->assertDatabaseHas('billing_invoices', ['status' => 'FAILED', 'failureReason' => 'Automatic card renewal failed']);
        $this->assertSame(1, collect(Http::recorded())->filter(fn (array $record): bool => $this->isCharge($record[0]))->count());
    }

    public function test_cancellation_stops_future_charges_and_duplicate_settlement_cannot_reenable_it(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);
        $end = $subscription->currentPeriodEnd->toDateTimeString();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)->delete(route('billing.auto-renew.destroy'))
            ->assertRedirect(route('billing'))->assertSessionHasNoErrors();
        $payment = app(PaymentManager::class)->active()->verifyByReference($invoice->fresh()->billingMetadata['attemptReference']);
        app(BillingService::class)->settlePayment($payment, app(PaymentManager::class)->active(), app(SaleRecorder::class));
        $this->travelTo(Carbon::parse($end)->addMinute());
        app(RecurringBilling::class)->process($subscription);

        $this->assertFalse($subscription->fresh()->autoRenew);
        $this->assertNull($subscription->fresh()->recurringAuthorization);
        $this->assertSame($end, $subscription->fresh()->currentPeriodEnd->toDateTimeString());
        Http::assertNotSent(fn (ClientRequest $request): bool => $this->isCharge($request));
    }

    public function test_delayed_initial_payment_after_cancellation_cannot_enable_renewal(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $this->start($owner, $invoice);
        $subscription = Subscription::query()->where('tenantId', $owner->tenantId)->firstOrFail();
        app(RecurringBilling::class)->cancel($subscription);
        $this->settle($invoice, 'paystack');

        $this->assertSame('PAID', $invoice->fresh()->status);
        $this->assertFalse($subscription->fresh()->autoRenew);
    }

    #[TestWith(['price'])]
    #[TestWith(['provider'])]
    #[TestWith(['credentials'])]
    #[TestWith(['closed'])]
    public function test_changed_conditions_pause_auto_renewal_without_charging(string $change): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);
        $this->travelTo($subscription->currentPeriodEnd->copy()->addMinute());
        match ($change) {
            'price' => Plan::query()->whereKey($subscription->planId)->update(['monthlyPrice' => 1200]),
            'provider' => PlatformSetting::writePayment(['provider' => 'flutterwave', 'platform_split' => 0, 'recurring_enabled' => true]),
            'credentials' => PlatformSetting::writePayment(['provider' => 'paystack', 'platform_split' => 0, 'paystack' => ['secret_key' => 'sk_test_replaced']]),
            'closed' => Tenant::query()->whereKey($owner->tenantId)->update(['isActive' => false]),
        };

        app(RecurringBilling::class)->process($subscription);

        $this->assertFalse($subscription->fresh()->autoRenew);
        Http::assertNotSent(fn (ClientRequest $request): bool => $this->isCharge($request));
    }

    public function test_reminder_is_sent_once_for_the_renewal_period(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);
        $this->travelTo($subscription->currentPeriodEnd->copy()->subDays(2));

        app(RecurringBilling::class)->sendReminder($subscription);
        app(RecurringBilling::class)->sendReminder($subscription->fresh());

        $this->assertSame(1, Notification::query()->where('message', 'like', 'Your software subscription will renew%')->count());
        Http::assertNotSent(fn (ClientRequest $request): bool => $this->isCharge($request));
    }

    #[TestWith(['overpayment'])]
    #[TestWith(['transfer'])]
    public function test_invoice_payment_is_verified_for_exact_amount_and_card_channel(string $invalid): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $this->start($owner, $invoice);
        if ($invalid === 'overpayment') {
            $this->verificationAmount = 1001;
        } else {
            $this->channel = 'bank_transfer';
        }
        $gateway = app(PaymentManager::class)->active();
        $payment = $gateway->verifyByReference($invoice->fresh()->billingMetadata['attemptReference'] ?? $invoice->invoiceNumber);

        $this->assertNull(app(BillingService::class)->settlePayment($payment, $gateway, app(SaleRecorder::class)));
        $this->assertSame('PENDING', $invoice->fresh()->status);
        $this->assertFalse(Subscription::query()->firstOrFail()->autoRenew);
    }

    public function test_other_tenant_cannot_read_authorisation_or_cancel_it(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);
        $other = new User(['id' => 'other', 'tenantId' => 'other-tenant', 'name' => 'Other']);
        $other->setRelation('role', new Role(['name' => 'Owner']));

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($other)->get(route('billing.authorization', $invoice))->assertForbidden();
        $this->delete(route('billing.auto-renew.destroy'))->assertNotFound();
        $this->assertTrue($subscription->fresh()->autoRenew);
    }

    #[TestWith(['2027-01-31', 'MONTHLY', 31, '2027-02-28'])]
    #[TestWith(['2028-01-31', 'MONTHLY', 31, '2028-02-29'])]
    #[TestWith(['2027-02-28', 'MONTHLY', 31, '2027-03-31'])]
    #[TestWith(['2028-02-29', 'ANNUALLY', 29, '2029-02-28'])]
    public function test_renewal_dates_keep_the_authorised_billing_day(string $start, string $cycle, int $anchor, string $expected): void
    {
        $this->assertSame($expected, RecurringBilling::periodEnd(Carbon::parse($start), $cycle, $anchor)->toDateString());
    }

    public function test_scheduled_command_reconciles_and_does_not_double_submit(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);
        $this->travelTo($subscription->currentPeriodEnd->copy()->addMinute());

        $this->artisan('subscriptions:renew', ['--no-interaction' => true])->assertExitCode(0);
        $this->artisan('subscriptions:renew', ['--no-interaction' => true])->assertExitCode(0);

        $this->assertSame(1, collect(Http::recorded())->filter(fn (array $record): bool => $this->isCharge($record[0]))->count());
    }

    #[TestWith(['paystack'])]
    #[TestWith(['flutterwave'])]
    public function test_upgrading_from_5000_to_15000_replaces_the_renewal_amount(string $provider): void
    {
        [$owner, $invoice] = $this->shop($provider);
        Plan::query()->whereKey($invoice->gatewayRef)->update(['monthlyPrice' => 5000]);
        $invoice->update(['amount' => 5000]);
        $this->verificationAmount = 5000;
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice, $provider);
        $upgrade = Plan::query()->create(['name' => 'Premium', 'tier' => 'ENTERPRISE', 'monthlyPrice' => 15000,
            'quarterlyPrice' => 45000, 'annualPrice' => 180000, 'features' => [], 'updatedAt' => now()]);
        $this->travel(1)->days();
        $this->actingAs($owner)->post(route('billing.choose'), ['plan' => $upgrade->id, 'cycle' => 'MONTHLY', 'amount' => 5000])
            ->assertRedirect()->assertSessionHasNoErrors();
        $newInvoice = BillingInvoice::query()->where('id', '!=', $invoice->id)->firstOrFail();
        $this->assertSame('15000.00', $newInvoice->amount);
        $this->get(route('billing.pay', $newInvoice))->assertOk()->assertSee('NGN 15000.00');
        $this->start($owner, $newInvoice);
        $this->assertFalse($subscription->fresh()->autoRenew);
        $this->verificationAmount = 15000;
        $this->settle($newInvoice, $provider);
        $subscription->refresh();
        $this->assertSame($upgrade->id, $subscription->planId);
        $this->assertSame('15000.00', $subscription->recurringAmount);
        $this->get(route('billing.invoice', $invoice))->assertOk()->assertSee('Grower');
        $this->travelTo($subscription->currentPeriodEnd->copy()->addMinute());

        app(RecurringBilling::class)->process($subscription);

        Http::assertSent(fn (ClientRequest $request): bool => $this->isCharge($request)
            && (float) $request['amount'] === ($provider === 'paystack' ? 1500000.0 : 15000.0));
        $this->assertSame('15000.00', $subscription->fresh()->recurringAmount);
    }

    #[TestWith(['paystack', 1])]
    #[TestWith(['paystack', 2])]
    #[TestWith(['flutterwave', 2])]
    public function test_agreed_introductory_schedule_expires_without_repeating_a_discount(string $provider, int $periods): void
    {
        [$owner, $invoice] = $this->shop($provider);
        $plan = Plan::query()->findOrFail($invoice->gatewayRef);
        $plan->update(['monthlyPrice' => 15000, 'introductoryPricing' => ['MONTHLY' => ['amount' => '5000.00', 'periods' => $periods]]]);
        $this->fakeGateway();
        $this->verificationAmount = 5000;
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)->post(route('billing.choose'), ['plan' => $plan->id, 'cycle' => 'MONTHLY'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('5000.00', $invoice->amount);
        $this->get(route('billing.pay', $invoice))->assertOk()->assertSee('then NGN 15,000.00');
        $subscription = $this->activate($owner, $invoice, $provider);
        $this->get(route('billing.authorization', $invoice))->assertOk()->assertSee('then NGN 15,000.00');
        $this->get(route('billing'))->assertOk()->assertSee('Upcoming schedule:');
        for ($index = 1; $index <= $periods; $index++) {
            $expected = $index < $periods ? 5000.0 : 15000.0;
            $this->assertEquals($expected, $subscription->fresh()->recurringAmount);
            $this->verificationAmount = $expected;
            $this->travelTo($subscription->fresh()->currentPeriodEnd->copy()->addMinute());
            app(RecurringBilling::class)->process($subscription);
            $renewal = BillingInvoice::query()->where('invoiceNumber', 'like', 'REN-%')->where('periodStart', $subscription->fresh()->currentPeriodStart)->firstOrFail();
            $this->assertSame('PAID', $renewal->status);
            $this->assertEquals($expected, $renewal->amount);
            $this->verificationAmount = 5000;
            $this->settle($invoice, $provider);
        }
        $this->assertSame(0, $subscription->fresh()->billingPricing['introRemaining']);
        $this->assertSame('15000.00', $subscription->fresh()->recurringAmount);
        $quote = SubscriptionPricing::quote($plan->fresh(), 'MONTHLY', $owner->tenantId);
        $this->assertSame(15000.0, SubscriptionPricing::amount($quote));
        $this->assertSame($periods, collect(Http::recorded())->filter(fn (array $record): bool => $this->isCharge($record[0]))->count());
    }

    public function test_upgrade_without_fresh_opt_in_disables_the_previous_card_schedule(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);
        $newPlan = Plan::query()->create(['name' => 'Upgrade', 'tier' => 'ENTERPRISE', 'monthlyPrice' => 15000,
            'quarterlyPrice' => 45000, 'annualPrice' => 180000, 'features' => [], 'updatedAt' => now()]);
        $this->travel(1)->days();
        $this->actingAs($owner)->post(route('billing.choose'), ['plan' => $newPlan->id, 'cycle' => 'MONTHLY'])->assertRedirect();
        $upgrade = BillingInvoice::query()->where('id', '!=', $invoice->id)->firstOrFail();
        $this->start($owner, $upgrade, false);
        $this->verificationAmount = 15000;
        $this->settle($upgrade, 'paystack');

        $this->assertSame($newPlan->id, $subscription->fresh()->planId);
        $this->assertFalse($subscription->fresh()->autoRenew);
        $this->assertNull($subscription->fresh()->recurringAuthorization);
    }

    public function test_a_late_payment_from_an_older_checkout_cannot_restore_the_previous_plan(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $this->start($owner, $invoice);
        $plan = Plan::query()->create(['name' => 'Upgrade', 'tier' => 'ENTERPRISE', 'monthlyPrice' => 15000,
            'quarterlyPrice' => 45000, 'annualPrice' => 180000, 'features' => [], 'updatedAt' => now()]);
        $this->actingAs($owner)->post(route('billing.choose'), ['plan' => $plan->id, 'cycle' => 'MONTHLY'])->assertRedirect();
        $upgrade = BillingInvoice::query()->where('id', '!=', $invoice->id)->firstOrFail();
        $this->verificationAmount = 15000;
        $subscription = $this->activate($owner, $upgrade);
        $this->verificationAmount = 1000;

        $this->settle($invoice, 'paystack');

        $this->assertSame($plan->id, $subscription->fresh()->planId);
        $this->assertSame('15000.00', $subscription->fresh()->recurringAmount);
        $this->assertSame('PAID', $invoice->fresh()->status);
    }

    public function test_admin_can_save_a_valid_introductory_offer_and_invalid_offers_are_rejected(): void
    {
        [$owner, $invoice] = $this->shop();
        $admin = new User(['id' => 'admin', 'isSuperAdmin' => true]);
        $plan = Plan::query()->findOrFail($invoice->gatewayRef);
        $data = ['name' => $plan->name, 'tier' => $plan->tier, 'monthlyPrice' => 15000, 'quarterlyPrice' => 45000,
            'annualPrice' => 180000, 'maxBranches' => 1, 'maxUsers' => 5, 'maxProducts' => 500,
            'introductoryPricing' => ['MONTHLY' => ['amount' => 5000, 'periods' => 2]]];
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin)->put(route('admin.plans.update', $plan), $data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['MONTHLY' => ['amount' => '5000.00', 'periods' => 2]], $plan->fresh()->introductoryPricing);
        $this->get(route('admin.billing'))->assertOk()->assertSee('Introductory pricing (optional)');
        $data['introductoryPricing']['MONTHLY']['amount'] = 20000;
        $this->putJson(route('admin.plans.update', $plan), $data)->assertUnprocessable()->assertJsonValidationErrors('introductoryPricing.MONTHLY.amount');
        $this->actingAs($owner)->putJson(route('admin.plans.update', $plan), $data)->assertForbidden();
    }

    public function test_failed_discounted_renewal_preserves_remaining_discount_for_manual_payment(): void
    {
        [$owner, $invoice] = $this->shop();
        $plan = Plan::query()->findOrFail($invoice->gatewayRef);
        $plan->update(['monthlyPrice' => 15000, 'introductoryPricing' => ['MONTHLY' => ['amount' => '5000.00', 'periods' => 2]]]);
        $this->fakeGateway();
        $this->verificationAmount = 5000;
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)->post(route('billing.choose'), ['plan' => $plan->id, 'cycle' => 'MONTHLY'])->assertRedirect();
        $subscription = $this->activate($owner, $invoice->fresh());
        $this->travelTo($subscription->currentPeriodEnd->copy()->addMinute());
        $this->verificationStatus = 'failed';
        app(RecurringBilling::class)->process($subscription);
        $this->assertSame(1, $subscription->fresh()->billingPricing['introRemaining']);
        $subscription->update(['status' => 'PAST_DUE']);
        $this->actingAs($owner)->post(route('billing.choose'), ['plan' => $plan->id, 'cycle' => 'MONTHLY'])->assertRedirect();
        $manual = BillingInvoice::query()->where('status', 'PENDING')->firstOrFail();
        $this->assertSame('5000.00', $manual->amount);
        $this->verificationStatus = 'success';

        $this->activate($owner, $manual);

        $this->assertSame(0, $subscription->fresh()->billingPricing['introRemaining']);
        $this->assertSame('15000.00', $subscription->fresh()->recurringAmount);
    }

    public function test_unapproved_renewal_schedule_cannot_change_the_card_charge(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);
        $subscription->update(['billingPricing' => array_merge($subscription->billingPricing, ['introAmount' => '2000.00'])]);
        $this->travelTo($subscription->currentPeriodEnd->copy()->addMinute());

        app(RecurringBilling::class)->process($subscription);

        $this->assertFalse($subscription->fresh()->autoRenew);
        Http::assertNotSent(fn (ClientRequest $request): bool => $this->isCharge($request));
    }

    public function test_confirmed_switch_to_a_shorter_interval_starts_the_selected_period(): void
    {
        [$owner, $invoice] = $this->shop();
        $invoice->update(['billingCycle' => 'QUARTERLY', 'amount' => 3000, 'periodEnd' => now()->addMonthsNoOverflow(3)]);
        $this->verificationAmount = 3000;
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);
        $this->travel(1)->days();
        $this->actingAs($owner)->post(route('billing.choose'), ['plan' => $subscription->planId, 'cycle' => 'MONTHLY'])->assertRedirect();
        $monthly = BillingInvoice::query()->where('id', '!=', $invoice->id)->firstOrFail();
        $this->verificationAmount = 1000;

        $this->activate($owner, $monthly);

        $this->assertSame('MONTHLY', $subscription->fresh()->billingCycle);
        $this->assertSame('1000.00', $subscription->fresh()->recurringAmount);
        $this->assertTrue($subscription->fresh()->currentPeriodEnd->equalTo($monthly->periodEnd));
    }

    #[TestWith(['paystack'])]
    #[TestWith(['flutterwave'])]
    public function test_successful_auto_renewal_emails_one_receipt_to_the_owner(string $provider): void
    {
        [$owner, $invoice] = $this->shop($provider);
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice, $provider);
        config(['services.zeptomail.key' => 'fake-mail-key', 'services.zeptomail.from' => 'billing@example.test']);
        Http::fake(['api.zeptomail.com/v1.1/email' => Http::response(['message' => 'Accepted'])]);
        $this->travelTo($subscription->currentPeriodEnd->copy()->addMinute());

        app(RecurringBilling::class)->process($subscription);
        $renewal = BillingInvoice::query()->where('invoiceNumber', 'like', 'REN-%')->firstOrFail();
        app(RecurringBilling::class)->reconcile($renewal);
        $this->settle($renewal, $provider);

        $receipts = collect(Http::recorded())->filter(fn (array $record): bool => $record[0]->url() === 'https://api.zeptomail.com/v1.1/email'
            && $record[0]['subject'] === 'SalesDock renewal payment received');
        $this->assertCount(1, $receipts);
        $request = $receipts->first()[0];
        $this->assertSame($owner->email, $request['to'][0]['email_address']['address']);
        $this->assertStringContainsString('NGN 1,000.00', $request['htmlbody']);
        $this->assertStringContainsString($renewal->invoiceNumber, $request['htmlbody']);
        $this->assertStringContainsString('Grower', $request['htmlbody']);
        $this->assertStringContainsString('Next renewal', $request['htmlbody']);
        $this->assertStringContainsString('02 Jan 2027', $request['htmlbody']);
        $this->assertStringNotContainsString('saved-token', $request['htmlbody']);
        $this->assertSame('PAID', $renewal->fresh()->status);
    }

    public function test_mail_provider_failure_does_not_reverse_or_repeat_a_successful_renewal(): void
    {
        [$owner, $invoice] = $this->shop();
        $this->fakeGateway();
        $subscription = $this->activate($owner, $invoice);
        config(['services.zeptomail.key' => 'fake-mail-key', 'services.zeptomail.from' => 'billing@example.test']);
        Http::fake(['api.zeptomail.com/v1.1/email' => Http::response(['message' => 'Unavailable'], 503)]);
        $this->travelTo($subscription->currentPeriodEnd->copy()->addMinute());

        app(RecurringBilling::class)->process($subscription);
        app(RecurringBilling::class)->process($subscription);

        $this->assertSame(2, BillingInvoice::query()->where('status', 'PAID')->count());
        $this->assertSame('ACTIVE', $subscription->fresh()->status);
        $this->assertSame(1, collect(Http::recorded())->filter(fn (array $record): bool => $this->isCharge($record[0]))->count());
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://api.zeptomail.com/v1.1/email'
            && $request['subject'] === 'SalesDock renewal payment received');
    }

    /** @return array{User, BillingInvoice} */
    private function shop(string $provider = 'paystack'): array
    {
        $tenant = Tenant::query()->create(['name' => 'Shop', 'slug' => 'shop', 'email' => 'owner@example.test', 'isActive' => true]);
        $role = Role::query()->create(['tenantId' => $tenant->id, 'name' => 'Owner', 'role' => 'ADMIN', 'permissions' => []]);
        $owner = User::query()->create(['tenantId' => $tenant->id, 'roleId' => $role->id, 'name' => 'Owner', 'email' => $tenant->email]);
        $plan = Plan::query()->create(['name' => 'Grower', 'tier' => 'PROFESSIONAL', 'monthlyPrice' => 1000, 'quarterlyPrice' => 3000, 'annualPrice' => 12000, 'features' => [], 'updatedAt' => now()]);
        Subscription::query()->create(['tenantId' => $tenant->id, 'planId' => $plan->id, 'status' => 'TRIALING', 'currentPeriodStart' => now(), 'currentPeriodEnd' => now()->addMonth(), 'trialEndsAt' => now()->addMonth(), 'updatedAt' => now()]);
        $invoice = BillingInvoice::query()->create(['tenantId' => $tenant->id, 'invoiceNumber' => 'INV-initial', 'billingCycle' => 'MONTHLY', 'amount' => 1000,
            'gateway' => strtoupper($provider), 'gatewayRef' => $plan->id, 'periodStart' => now(), 'periodEnd' => now()->addMonth(), 'dueDate' => now()->addMinutes(30)]);
        PlatformSetting::writePayment(['provider' => $provider, 'platform_split' => 0.015, 'recurring_enabled' => true,
            'paystack' => ['secret_key' => 'sk_test_saved'], 'flutterwave' => ['secret_key' => 'FLWSECK_TEST-saved-X', 'secret_hash' => 'hash']]);

        return [$owner, $invoice];
    }

    private function start(User $owner, BillingInvoice $invoice, bool $auto = true): void
    {
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('billing.start', $invoice->invoiceNumber), ['autoRenew' => $auto ? 1 : 0, 'authorizationVersion' => RecurringBilling::TERMS_VERSION])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    private function activate(User $owner, BillingInvoice $invoice, string $provider = 'paystack', bool $auto = true): Subscription
    {
        $this->start($owner, $invoice, $auto);
        $this->settle($invoice, $provider);

        return Subscription::query()->where('tenantId', $owner->tenantId)->firstOrFail();
    }

    private function settle(BillingInvoice $invoice, string $provider): void
    {
        $gateway = app(PaymentManager::class)->driver($provider);
        $payment = $gateway->verifyByReference($invoice->fresh()->billingMetadata['attemptReference'] ?? $invoice->invoiceNumber);
        $this->assertSame('invoice', app(BillingService::class)->settlePayment($payment, $gateway, app(SaleRecorder::class)));
    }

    private function isCharge(ClientRequest $request): bool
    {
        return str_contains($request->url(), '/charge_authorization') || str_contains($request->url(), '/tokenized-charges');
    }

    private function fakeGateway(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/test']]),
            'api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/test']]),
            'api.paystack.co/transaction/charge_authorization' => Http::response(['status' => true, 'data' => []]),
            'api.flutterwave.com/v3/tokenized-charges' => Http::response(['status' => 'success', 'data' => []]),
            'api.paystack.co/transaction/verify/*' => fn (ClientRequest $request) => $this->verification('paystack', rawurldecode(basename(parse_url($request->url(), PHP_URL_PATH)))),
            'api.flutterwave.com/v3/transactions/verify_by_reference?*' => fn (ClientRequest $request) => $this->verification('flutterwave', $request['tx_ref']),
        ]);
    }

    private function verification(string $provider, string $reference): PromiseInterface
    {
        if ($this->verificationUnavailable) {
            return Http::response(['message' => 'Unavailable'], 503);
        }
        $renewal = str_starts_with($reference, 'REN-');
        if ($provider === 'paystack') {
            return Http::response(['status' => true, 'data' => [
                'id' => $renewal ? 789 : 123, 'reference' => $reference, 'amount' => (int) ($this->verificationAmount * 100), 'currency' => 'NGN',
                'status' => $this->verificationStatus, 'channel' => $this->channel, 'paid_at' => now()->toISOString(),
                'customer' => ['email' => 'payer@example.test'], 'authorization' => ['authorization_code' => 'AUTH-saved-token', 'reusable' => $this->reusable, 'last4' => '4242', 'card_type' => 'visa', 'exp_month' => '12', 'exp_year' => '2030'],
            ]]);
        }

        return Http::response(['status' => 'success', 'data' => [
            'id' => $renewal ? 789 : 123, 'tx_ref' => $reference, 'amount' => $this->verificationAmount, 'currency' => 'NGN',
            'status' => $this->verificationStatus === 'success' ? 'successful' : $this->verificationStatus,
            'payment_type' => $this->channel, 'created_at' => now()->toISOString(), 'customer' => ['email' => 'payer@example.test'],
            'card' => ['token' => 'FLW-saved-token', 'last_4digits' => '4242', 'type' => 'visa', 'expiry' => '12/30'],
        ]]);
    }
}
