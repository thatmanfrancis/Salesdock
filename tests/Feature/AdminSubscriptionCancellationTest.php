<?php

namespace Tests\Feature;

use App\Http\Middleware\ShareWorkspace;
use App\Models\AuditLog;
use App\Models\BillingInvoice;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\StorefrontConfig;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentManager;
use App\Services\RecurringBilling;
use App\Services\SaleRecorder;
use App\Support\PlanFeatures;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\View\Factory;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminSubscriptionCancellationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'services.zeptomail.key' => '', 'services.zeptomail.from' => '']);
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')) ?: [], fn (string $path): bool => ! str_contains($path, 'create_branch_stocks_table')));
        $this->assertSame(0, Artisan::call('migrate', ['--path' => $paths, '--realpath' => true, '--no-interaction' => true]));
    }

    public function test_admin_cancellation_requires_phrase_and_password_and_preserves_merchant_records(): void
    {
        [$admin, $owner, $subscription, $invoice] = $this->shop();
        Http::preventStrayRequests();
        $tenant = $subscription->tenant;
        $end = $subscription->currentPeriodEnd->toISOString();
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin)->delete(route('admin.tenants.subscription.cancel', $tenant), $this->confirmation($tenant))
            ->assertRedirect()->assertSessionHasNoErrors();
        $subscription->refresh();

        $this->assertSame('CANCELLED', $subscription->status);
        $this->assertFalse($subscription->autoRenew);
        $this->assertNull($subscription->recurringAuthorization);
        $this->assertNull($subscription->nextBillingDate);
        $this->assertNotNull($subscription->cancelledAt);
        $this->assertSame($end, $subscription->currentPeriodEnd->toISOString());
        $this->assertSame('PAID', $invoice->fresh()->status);
        $this->assertTrue($tenant->fresh()->isActive);
        $this->assertNotNull($owner->fresh());
        $audit = AuditLog::query()->firstOrFail();
        $this->assertSame('SUBSCRIPTION_CANCELLED_BY_ADMIN', $audit->details['type']);
        $this->assertSame($admin->id, $audit->userId);
        $this->assertStringNotContainsString('Admin-password', json_encode($audit->details));
        $this->assertSame(1, Notification::query()->where('title', 'Subscription cancelled by SalesDock')->count());
        $this->travelTo($subscription->currentPeriodEnd->copy()->addMinute());
        app(RecurringBilling::class)->process($subscription);
        Http::assertNothingSent();
        $this->assertTrue($subscription->fresh()->usesBasicAccess());
    }

    #[TestWith(['wrong-password'])]
    #[TestWith(['wrong-merchant'])]
    #[TestWith(['missing-delete'])]
    public function test_incorrect_confirmation_cannot_cancel_a_subscription(string $invalid): void
    {
        [$admin, $owner, $subscription] = $this->shop();
        $data = $this->confirmation($subscription->tenant);
        if ($invalid === 'wrong-password') {
            $data['password'] = 'Wrong-password';
        } else {
            $data['confirmation'] = $invalid === 'wrong-merchant' ? 'DELETE Another shop' : $subscription->tenant->name;
        }
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin)->deleteJson(route('admin.tenants.subscription.cancel', $subscription->tenant), $data)
            ->assertUnprocessable()->assertJsonValidationErrors($invalid === 'wrong-password' ? 'password' : 'confirmation')->assertSessionMissing('_old_input.password');
        $this->assertSame('ACTIVE', $subscription->fresh()->status);
        $this->assertTrue($subscription->fresh()->autoRenew);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_merchants_and_guests_cannot_cancel_subscriptions_through_admin_routes(): void
    {
        [$admin, $owner, $subscription] = $this->shop();
        $url = route('admin.tenants.subscription.cancel', $subscription->tenant);
        $this->delete($url, $this->confirmation($subscription->tenant))->assertRedirect(route('login'));
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)->deleteJson($url, $this->confirmation($subscription->tenant))->assertForbidden();
        $this->assertSame('ACTIVE', $subscription->fresh()->status);
    }

    public function test_cancel_controls_are_visible_on_admin_subscription_and_merchant_pages(): void
    {
        [$admin, $owner, $subscription] = $this->shop();
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin)->get(route('admin.subscriptions'))
            ->assertOk()->assertSee('Cancel subscription')->assertSee('DELETE Shop')->assertSee('Your admin login password');
        $this->get(route('admin.tenants.show', $subscription->tenant))->assertOk()->assertSee('Confirm cancellation')->assertSee('The merchant can keep selling and subscribe again later.');
    }

    public function test_duplicate_cancellation_is_idempotent(): void
    {
        [$admin, $owner, $subscription] = $this->shop();
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin);
        $this->delete(route('admin.tenants.subscription.cancel', $subscription->tenant), $this->confirmation($subscription->tenant))->assertRedirect();
        $version = $subscription->fresh()->recurringConsent['adminCancellationVersion'];
        $this->delete(route('admin.tenants.subscription.cancel', $subscription->tenant), $this->confirmation($subscription->tenant))->assertRedirect();
        $this->assertSame($version, $subscription->fresh()->recurringConsent['adminCancellationVersion']);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_platform_super_admin_role_can_cancel_without_the_legacy_admin_flag(): void
    {
        [$admin, $owner, $subscription] = $this->shop();
        $role = Role::query()->create(['tenantId' => $admin->tenantId, 'name' => 'Platform admin', 'role' => 'SUPER_ADMIN', 'permissions' => []]);
        $admin->update(['roleId' => $role->id, 'isSuperAdmin' => false]);
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin)->delete(route('admin.tenants.subscription.cancel', $subscription->tenant), $this->confirmation($subscription->tenant))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $subscription->fresh()->status);
    }

    public function test_repeated_admin_password_attempts_are_rate_limited(): void
    {
        [$admin, $owner, $subscription] = $this->shop();
        $data = $this->confirmation($subscription->tenant);
        $data['password'] = 'Wrong-password';
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->deleteJson(route('admin.tenants.subscription.cancel', $subscription->tenant), $data)->assertUnprocessable();
        }
        $this->deleteJson(route('admin.tenants.subscription.cancel', $subscription->tenant), $data)->assertTooManyRequests();
        $this->assertSame('ACTIVE', $subscription->fresh()->status);
    }

    #[TestWith(['manual'])]
    #[TestWith(['automatic'])]
    public function test_late_payment_cannot_reactivate_an_admin_cancelled_subscription(string $kind): void
    {
        [$admin, $owner, $subscription, $paid] = $this->shop();
        $pending = $this->pending($subscription);
        $pending->update(['billingMetadata' => array_merge($pending->billingMetadata, ['kind' => $kind])]);
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin)->delete(route('admin.tenants.subscription.cancel', $subscription->tenant), $this->confirmation($subscription->tenant))->assertRedirect();

        $this->settle($pending);

        $this->assertSame('PAID', $pending->fresh()->status);
        $this->assertSame('CANCELLED', $subscription->fresh()->status);
        $this->assertFalse($subscription->fresh()->autoRenew);
        $this->assertTrue(Notification::query()->where('title', 'Subscription payment received for review')->exists());
    }

    public function test_fresh_checkout_after_cancellation_can_activate_a_new_paid_subscription(): void
    {
        [$admin, $owner, $subscription, $paid] = $this->shop();
        $subscription->plan->update(['features' => ['POS Terminal', 'Storefront / Online Store']]);
        $old = $this->pending($subscription);
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin)->delete(route('admin.tenants.subscription.cancel', $subscription->tenant), $this->confirmation($subscription->tenant))->assertRedirect();
        Http::preventStrayRequests();
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/fake']])]);
        $this->actingAs($owner)->post(route('billing.start', $old->invoiceNumber), ['autoRenew' => 0])->assertSessionHasErrors('payment');
        $this->actingAs($owner)->post(route('billing.choose'), ['plan' => $subscription->planId, 'cycle' => 'MONTHLY'])->assertRedirect()->assertSessionHasNoErrors();
        $fresh = BillingInvoice::query()->whereNotIn('id', [$paid->id, $old->id])->firstOrFail();
        $this->post(route('billing.start', $fresh->invoiceNumber), ['autoRenew' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->settle($fresh);
        $this->assertSame('ACTIVE', $subscription->fresh()->status);
        $this->assertSame(['POS Terminal', 'Storefront / Online Store'], $subscription->fresh()->features());
        $this->assertNotEmpty($subscription->fresh()->recurringConsent['adminCancellationVersion']);
        $this->settle($old);
        $this->assertTrue($subscription->fresh()->currentPeriodEnd->equalTo($fresh->periodEnd));
    }

    private function pending(Subscription $subscription): BillingInvoice
    {
        return BillingInvoice::query()->create(['tenantId' => $subscription->tenantId, 'subscriptionId' => $subscription->id,
            'invoiceNumber' => 'INV-old', 'billingCycle' => 'MONTHLY', 'amount' => 1000, 'gateway' => 'PAYSTACK', 'gatewayRef' => $subscription->planId,
            'periodStart' => now(), 'periodEnd' => now()->addYear(), 'status' => 'PENDING', 'dueDate' => now()->addMinutes(30),
            'billingMetadata' => ['kind' => 'manual', 'provider' => 'paystack', 'planId' => $subscription->planId, 'checkoutState' => 'READY', 'checkoutUrl' => 'https://checkout.paystack.com/old']]);
    }

    private function settle(BillingInvoice $invoice): void
    {
        $this->assertSame('invoice', app(BillingService::class)->settlePayment(['id' => $invoice->id, 'tx_ref' => $invoice->invoiceNumber,
            'amount' => 1000, 'currency' => 'NGN', 'status' => 'success', 'method' => 'CARD', 'gateway' => 'paystack'], app(PaymentManager::class)->driver('paystack'), app(SaleRecorder::class)));
    }

    public function test_final_review_confirmation_is_required_before_cancellation(): void
    {
        [$admin, $owner, $subscription] = $this->shop();
        $data = $this->confirmation($subscription->tenant);
        $data['final_confirmation'] = 0;
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin)->deleteJson(route('admin.tenants.subscription.cancel', $subscription->tenant), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('final_confirmation')->assertSessionMissing('subscription_cancelled');
        $this->assertSame('ACTIVE', $subscription->fresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_success_modal_names_the_merchant_only_after_the_cancellation_succeeds(): void
    {
        [$admin, $owner, $subscription] = $this->shop();
        $url = route('admin.subscriptions');
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin)->from($url)
            ->delete(route('admin.tenants.subscription.cancel', $subscription->tenant), $this->confirmation($subscription->tenant))
            ->assertRedirect($url)->assertSessionHas('subscription_cancelled.merchantName', 'Shop');
        $this->get($url)->assertOk()->assertSee('data-subscription-cancelled', false)->assertSee("Shop's subscription has been cancelled", false)
            ->assertSee('To subscribe again')->assertSee('data-cancel-result-close', false);
    }

    public function test_cancellation_removes_paid_features_and_shows_default_access_instead_of_the_old_plan(): void
    {
        [$admin, $owner, $subscription, $invoice] = $this->shop();
        $subscription->plan->update(['features' => ['POS Terminal', 'Advanced Inventory', 'Advanced Analytics', 'Storefront / Online Store']]);
        $this->assertNotEmpty($subscription->fresh()->features());
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin)->delete(route('admin.tenants.subscription.cancel', $subscription->tenant), $this->confirmation($subscription->tenant))->assertRedirect();

        $this->assertSame(PlanFeatures::basic(), $subscription->fresh()->features());
        $this->assertFalse($subscription->fresh()->hasPlanAccess());
        $this->get(route('admin.subscriptions'))->assertOk()->assertSee('Basic — free access');
        $this->get(route('admin.tenants.show', $subscription->tenant))->assertOk()->assertSee('Basic — free access');
        $this->withMiddleware(ShareWorkspace::class)->actingAs($owner)->get(route('billing'))->assertOk()
            ->assertSee('Basic — free access')->assertSee('Keep selling with POS')->assertSee('Subscribe to this plan')->assertDontSee('plan-card current', false);
        $views = app(Factory::class);
        $navigationPaths = array_column($views->shared('nav'), 'path');
        $this->assertFalse($views->shared('opensStorefront'));
        $this->assertContains('/pos', $navigationPaths);
        $this->assertContains('/products', $navigationPaths);
        $this->assertNotContains('/analytics', $navigationPaths);
        foreach (['/analytics', '/staff', '/payroll', '/promotions', '/inventory/purchase-orders'] as $path) {
            $this->get($path)->assertRedirect(route('not-eligible'));
        }
        $this->get(route('billing.invoice', $invoice))->assertOk()->assertSee('INV-paid');
        $this->assertSame('PAID', $invoice->fresh()->status);
    }

    #[TestWith(['CANCELLED'])]
    #[TestWith(['SUSPENDED'])]
    #[TestWith(['EXPIRED'])]
    #[TestWith(['TRIALING'])]
    public function test_inactive_subscriptions_do_not_keep_the_old_plans_entitlements(string $status): void
    {
        [$admin, $owner, $subscription] = $this->shop();
        $subscription->plan->update(['features' => ['POS Terminal', 'Advanced Analytics']]);
        $subscription->update(['status' => $status, 'trialEndsAt' => now()->subMinute()]);

        $this->assertSame($status === 'SUSPENDED' ? [] : PlanFeatures::basic(), $subscription->fresh()->features());
        $this->assertFalse($subscription->fresh()->hasPlanAccess());
    }

    public function test_cancelled_storefront_cannot_take_new_orders_but_past_order_receipts_remain_accessible(): void
    {
        [$admin, $owner, $subscription] = $this->shop();
        Http::preventStrayRequests();
        StorefrontConfig::query()->create(['tenantId' => $owner->tenantId, 'storeName' => 'Shop', 'isPublic' => true, 'updatedAt' => now()]);
        $order = Order::query()->create(['tenantId' => $owner->tenantId, 'status' => 'COMPLETED', 'channel' => 'ONLINE', 'totalAmount' => 1000, 'netAmount' => 1000, 'paymentRef' => 'SALE-old']);
        $this->get(route('store.show', ['slug' => 'shop']))->assertOk();
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($admin)->delete(route('admin.tenants.subscription.cancel', $subscription->tenant), $this->confirmation($subscription->tenant))->assertRedirect();

        $this->get(route('store.show', ['slug' => 'shop']))->assertNotFound();
        $this->get(route('store.checkout', ['slug' => 'shop']))->assertNotFound();
        $this->post(route('store.checkout.store', ['slug' => 'shop']), [])->assertNotFound();
        $this->get(route('store.order', ['slug' => 'shop', 'ref' => $order->paymentRef]))->assertOk();
        $this->assertSame(1, Order::query()->count());
        Http::assertNothingSent();
    }

    public function test_expiry_command_moves_a_shop_to_basic_and_stops_future_renewals(): void
    {
        [$admin, $owner, $subscription] = $this->shop();
        $subscription->update(['status' => 'PAST_DUE', 'gracePeriodEndsAt' => now()->subMinute()]);

        $this->assertSame(0, Artisan::call('subscriptions:downgrade', ['--no-interaction' => true]));

        $this->assertSame('EXPIRED', $subscription->fresh()->status);
        $this->assertTrue($subscription->fresh()->usesBasicAccess());
        $this->assertSame(PlanFeatures::basic(), $subscription->fresh()->features());
        $this->assertFalse($subscription->fresh()->autoRenew);
        $this->assertNull($subscription->fresh()->recurringAuthorization);
    }

    public function test_suspension_still_blocks_pos_instead_of_granting_basic_selling_access(): void
    {
        [$admin, $owner, $subscription] = $this->shop();
        $subscription->update(['status' => 'SUSPENDED']);
        $this->assertFalse($subscription->fresh()->usesBasicAccess());
        $this->assertSame([], $subscription->fresh()->features());
        $this->actingAs($owner)->postJson(route('pos.store'), ['qty' => [], 'method' => 'CASH'])->assertRedirect(route('billing'));
    }

    /** @return array{confirmation: string, password: string, final_confirmation: int} */
    private function confirmation(Tenant $tenant): array
    {
        return ['confirmation' => 'DELETE '.$tenant->name, 'password' => 'Admin-password', 'final_confirmation' => 1];
    }

    /** @return array{User, User, Subscription, BillingInvoice} */
    private function shop(): array
    {
        $tenant = Tenant::query()->create(['name' => 'Shop', 'slug' => 'shop', 'email' => 'owner@example.test', 'isActive' => true]);
        $role = Role::query()->create(['tenantId' => $tenant->id, 'name' => 'Owner', 'role' => 'ADMIN', 'permissions' => []]);
        $owner = User::query()->create(['tenantId' => $tenant->id, 'roleId' => $role->id, 'name' => 'Owner', 'email' => $tenant->email]);
        $admin = User::query()->create(['tenantId' => $tenant->id, 'roleId' => $role->id, 'name' => 'Platform Admin', 'email' => 'admin@example.test', 'isSuperAdmin' => true, 'passwordHash' => Hash::make('Admin-password')]);
        $plan = Plan::query()->create(['name' => 'Plan', 'tier' => 'PROFESSIONAL', 'monthlyPrice' => 1000, 'quarterlyPrice' => 3000, 'annualPrice' => 12000, 'features' => []]);
        $subscription = Subscription::query()->create(['tenantId' => $tenant->id, 'planId' => $plan->id, 'status' => 'ACTIVE', 'billingCycle' => 'MONTHLY',
            'currentPeriodStart' => now(), 'currentPeriodEnd' => now()->addMonth(), 'nextBillingDate' => now()->addMonth(),
            'autoRenew' => true, 'recurringAmount' => 1000, 'recurringProvider' => 'paystack', 'recurringAuthorization' => ['token' => 'test-token']]);
        $invoice = BillingInvoice::query()->create(['tenantId' => $tenant->id, 'subscriptionId' => $subscription->id, 'invoiceNumber' => 'INV-paid',
            'status' => 'PAID', 'billingCycle' => 'MONTHLY', 'amount' => 1000, 'gateway' => 'PAYSTACK', 'gatewayRef' => '123',
            'periodStart' => now(), 'periodEnd' => $subscription->currentPeriodEnd, 'paidAt' => now(), 'dueDate' => now()]);
        PlatformSetting::writePayment(['provider' => 'paystack', 'platform_split' => 0, 'recurring_enabled' => true, 'paystack' => ['secret_key' => 'sk_test_fake']]);

        return [$admin, $owner, $subscription, $invoice];
    }
}
