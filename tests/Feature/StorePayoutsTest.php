<?php

namespace Tests\Feature;

use App\Http\Middleware\ShareWorkspace;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\StorefrontConfig;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StorePayoutsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $path): bool => ! str_contains($path, 'create_branch_stocks_table')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--no-interaction' => true])->assertExitCode(0);
    }

    #[TestWith(['paystack'])]
    #[TestWith(['flutterwave'])]
    public function test_owner_can_resolve_name_without_changing_payouts(string $provider): void
    {
        $owner = $this->owner($provider);
        $this->fakeBank();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->postJson(route('settings.bank-account.resolve'), ['bankCode' => '035', 'bankAccount' => '0123456789'])
            ->assertOk()->assertJsonPath('accountName', 'CONFIRMED SHOP OWNER')->assertHeader('Cache-Control', 'no-store, private');

        $this->assertDatabaseCount('storefront_config', 0);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => ($request['account_number'] ?? null) === '0123456789');
        $inactiveHost = $provider === 'paystack' ? 'api.flutterwave.com' : 'api.paystack.co';
        Http::assertNotSent(fn ($request): bool => parse_url($request->url(), PHP_URL_HOST) === $inactiveHost);
        $activeSecret = $provider === 'paystack' ? 'sk_test_example' : 'FLWSECK_TEST-example-X';
        Http::assertSent(fn ($request): bool => ($request['account_number'] ?? null) === '0123456789'
            && $request->hasHeader('Authorization', 'Bearer '.$activeSecret));
    }

    public function test_lookup_requires_authentication(): void
    {
        Http::preventStrayRequests();
        $this->postJson(route('settings.bank-account.resolve'), ['bankCode' => '035', 'bankAccount' => '0123456789'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_cashier_cannot_look_up_bank_accounts(): void
    {
        $owner = $this->owner();
        $owner->role->forceFill(['name' => 'Cashier', 'role' => 'CASHIER'])->save();
        Http::preventStrayRequests();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->postJson(route('settings.bank-account.resolve'), ['bankCode' => '035', 'bankAccount' => '0123456789'])->assertForbidden();

        Http::assertNothingSent();
    }

    #[TestWith(['123'])]
    #[TestWith(['01234567890'])]
    #[TestWith(['01234x6789'])]
    public function test_lookup_rejects_invalid_account_numbers_with_422(string $number): void
    {
        $owner = $this->owner();
        Http::preventStrayRequests();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->postJson(route('settings.bank-account.resolve'), ['bankCode' => '035', 'bankAccount' => $number])
            ->assertUnprocessable()->assertJsonValidationErrors('bankAccount');

        Http::assertNothingSent();
    }

    public function test_unknown_bank_cannot_be_verified(): void
    {
        $owner = $this->owner();
        $this->fakeBank();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->postJson(route('settings.bank-account.resolve'), ['bankCode' => 'unknown', 'bankAccount' => '0123456789'])
            ->assertUnprocessable()->assertJsonValidationErrors('bankAccount');

        Http::assertSentCount(1);
    }

    public function test_test_mode_rejection_explains_how_to_connect_a_real_account(): void
    {
        $owner = $this->owner('flutterwave');
        $this->fakeBank(false);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->postJson(route('settings.bank-account.resolve'), ['bankCode' => '035', 'bankAccount' => '0123456789'])
            ->assertUnprocessable()->assertJsonPath('errors.bankAccount.0', 'The payment provider is in test mode. Use a provider test bank account, or ask SalesDock to enable live payments before connecting a real account.');

        Http::assertSentCount(2);
    }

    public function test_typed_name_cannot_bypass_failed_verification_or_replace_existing_payouts(): void
    {
        $owner = $this->owner();
        $store = StorefrontConfig::query()->create(['tenantId' => $owner->tenantId, 'storeName' => 'Test shop', 'bankAccount' => '0000000001', 'accountName' => 'OLD OWNER', 'paystackSubaccountCode' => 'ACCT_existing', 'updatedAt' => now()]);
        $this->fakeBank(false);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('settings.update'), ['section' => 'payments', 'bankCode' => '035', 'bankAccount' => '0123456789', 'accountName' => 'UNVERIFIED NAME', 'password' => 'test-password'])
            ->assertSessionHasErrors('bankAccount');

        $this->assertSame('0000000001', $store->fresh()->bankAccount);
        $this->assertSame('OLD OWNER', $store->fresh()->accountName);
        Http::assertSentCount(2);
    }

    public function test_live_provider_rejection_does_not_claim_that_payments_are_in_test_mode(): void
    {
        $owner = $this->owner();
        PlatformSetting::writePayment(['provider' => 'paystack', 'platform_split' => 0.015, 'paystack' => ['secret_key' => 'sk_live_example']]);
        $this->fakeBank(false);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->postJson(route('settings.bank-account.resolve'), ['bankCode' => '035', 'bankAccount' => '0123456789'])
            ->assertUnprocessable()->assertJsonPath('errors.bankAccount.0', 'We could not verify this bank account. Check the selected bank and account number, then try again. Invalid account');

        Http::assertSentCount(2);
    }

    public function test_unconfigured_provider_cannot_claim_to_verify_an_account(): void
    {
        $owner = $this->owner();
        config(['services.paystack.secret_key' => '']);
        PlatformSetting::write('payment', ['provider' => 'paystack', 'platform_split' => 0, 'paystack' => ['secret_key' => '']]);
        Http::preventStrayRequests();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->postJson(route('settings.bank-account.resolve'), ['bankCode' => '035', 'bankAccount' => '0123456789'])
            ->assertServiceUnavailable()->assertJsonPath('errors.bankAccount.0', 'Account verification is unavailable until SalesDock configures the payment provider.');

        Http::assertNothingSent();
    }

    #[TestWith(['paystack', 401])]
    #[TestWith(['flutterwave', 403])]
    #[TestWith(['paystack', 500])]
    #[TestWith(['flutterwave', 503])]
    public function test_provider_configuration_and_outages_are_not_reported_as_invalid_accounts(string $provider, int $status): void
    {
        $owner = $this->owner($provider);
        Http::preventStrayRequests();
        Http::fake([
            'api.paystack.co/bank?*' => Http::response(['status' => true, 'data' => [['code' => '035', 'name' => 'Wema Bank']]]),
            'api.flutterwave.com/v3/banks/NG' => Http::response(['status' => 'success', 'data' => [['code' => '035', 'name' => 'Wema Bank']]]),
            'api.paystack.co/bank/resolve?*' => Http::response(['status' => false, 'message' => 'Provider unavailable'], $status),
            'api.flutterwave.com/v3/accounts/resolve' => Http::response(['status' => 'error', 'message' => 'Provider unavailable'], $status),
        ]);

        $response = $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->postJson(route('settings.bank-account.resolve'), ['bankCode' => '035', 'bankAccount' => '0123456789'])
            ->assertServiceUnavailable();

        $this->assertStringNotContainsString('test mode', $response->json('message'));
        $this->assertStringContainsString($status < 500 ? 'API credentials' : 'temporarily unavailable', $response->json('message'));
        Http::assertSentCount(2);
        $this->assertDatabaseCount('storefront_config', 0);
    }

    public function test_provider_connection_failure_returns_a_retry_message(): void
    {
        $owner = $this->owner();
        Http::preventStrayRequests();
        Http::fake([
            'api.paystack.co/bank?*' => Http::response(['status' => true, 'data' => [['code' => '035', 'name' => 'Wema Bank']]]),
            'api.paystack.co/bank/resolve?*' => Http::failedConnection(),
        ]);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->postJson(route('settings.bank-account.resolve'), ['bankCode' => '035', 'bankAccount' => '0123456789'])
            ->assertServiceUnavailable()->assertJsonPath('message', 'Bank verification is temporarily unavailable. Please try again shortly. Could not reach Paystack. Please try again in a moment.');

        Http::assertSentCount(2);
    }

    public function test_failed_subaccount_creation_preserves_the_saved_account(): void
    {
        $owner = $this->owner();
        $store = StorefrontConfig::query()->create(['tenantId' => $owner->tenantId, 'storeName' => 'Shop', 'bankAccount' => '0000000001', 'accountName' => 'OLD OWNER', 'updatedAt' => now()]);
        $this->fakeBank();
        Http::fake(['api.paystack.co/subaccount' => Http::response(['status' => false, 'message' => 'Payout setup unavailable'], 400)]);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('settings.update'), ['section' => 'payments', 'bankCode' => '035', 'bankAccount' => '0123456789', 'password' => 'test-password'])
            ->assertSessionHasErrors('bankAccount')->assertSessionMissing('_old_input.password');

        $this->assertSame('0000000001', $store->fresh()->bankAccount);
        $this->assertNull($store->fresh()->paystackSubaccountCode);
        Http::assertSentCount(3);
    }

    public function test_saving_bank_details_uses_provider_confirmed_name(): void
    {
        $owner = $this->owner();
        $this->fakeBank();
        Http::fake(['api.paystack.co/subaccount' => Http::response(['status' => true, 'data' => ['subaccount_code' => 'ACCT_new']])]);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('settings.update'), ['section' => 'payments', 'bankCode' => '035', 'bankAccount' => '0123456789', 'accountName' => 'TYPED NAME'])
            ->assertSessionHasNoErrors()->assertRedirect(route('settings', ['section' => 'payments']));

        $this->assertDatabaseHas('storefront_config', ['tenantId' => $owner->tenantId, 'accountName' => 'CONFIRMED SHOP OWNER', 'bankAccount' => '0123456789', 'paystackSubaccountCode' => 'ACCT_new']);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.paystack.co/subaccount' && $request['primary_contact_name'] === 'CONFIRMED SHOP OWNER');
    }

    #[TestWith(['paystack', 150.0])]
    #[TestWith(['flutterwave', 150.0])]
    #[TestWith(['paystack', 0.0])]
    #[TestWith(['flutterwave', 0.0])]
    public function test_storefront_settles_exact_order_fee_even_after_configured_rate_changes(string $provider, float $fee): void
    {
        $owner = $this->owner($provider);
        StorefrontConfig::query()->create(['tenantId' => $owner->tenantId, 'storeName' => 'Shop', 'isPublic' => true, 'paystackSubaccountCode' => 'ACCT_shop', 'flutterwaveSubaccountId' => 'RS_shop', 'updatedAt' => now()]);
        $order = Order::query()->create(['tenantId' => $owner->tenantId, 'paymentRef' => 'STORE-123', 'status' => 'PENDING', 'totalAmount' => 10000, 'netAmount' => 12000 + $fee, 'serviceCharge' => $fee, 'deliveryFee' => 2000, 'customerEmail' => 'customer@example.test']);
        Http::preventStrayRequests();
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/example']]),
            'api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/example']]),
        ]);

        $this->post(route('store.pay.start', ['slug' => $owner->tenant->slug, 'ref' => $order->paymentRef]))->assertRedirect();

        Http::assertSentCount(1);
        if ($provider === 'paystack') {
            Http::assertSent(fn ($request): bool => $request['subaccount'] === 'ACCT_shop' && $request['transaction_charge'] === (int) ($fee * 100) && $request['amount'] === (int) ((12000 + $fee) * 100));
        } else {
            Http::assertSent(fn ($request): bool => $request['subaccounts'][0]['id'] === 'RS_shop'
                && $request['subaccounts'][0]['transaction_charge_type'] === 'flat'
                && (float) $request['subaccounts'][0]['transaction_charge'] === $fee);
        }
    }

    #[TestWith(['payments'])]
    #[TestWith(['storefront'])]
    public function test_settings_prefill_saved_bank_details(string $section): void
    {
        $owner = $this->owner();
        $this->savedPayout($owner);
        $this->fakeBank();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->get(route('settings', ['section' => $section]))
            ->assertOk()->assertSee('name="bankCode" value="035"', false)
            ->assertSee('value="0000000001"', false)->assertSee('value="OLD OWNER"', false)
            ->assertSee('Confirm bank account change')->assertSee('autocomplete="current-password"', false);

        Http::assertSentCount(1);
    }

    #[TestWith(['payments', ''])]
    #[TestWith(['payments', 'wrong-password'])]
    #[TestWith(['storefront', ''])]
    #[TestWith(['storefront', 'wrong-password'])]
    public function test_account_replacement_requires_the_current_users_login_password(string $section, string $password): void
    {
        $owner = $this->owner();
        $store = $this->savedPayout($owner);
        $this->fakeBank();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('settings.update'), $this->payoutSettings($section) + ['password' => $password])
            ->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password');

        $this->assertSame('0000000001', $store->fresh()->bankAccount);
        $this->assertSame('ACCT_existing', $store->fresh()->paystackSubaccountCode);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/resolve') || str_contains($request->url(), '/subaccount'));
    }

    #[TestWith(['payments'])]
    #[TestWith(['storefront'])]
    public function test_correct_login_password_allows_account_replacement(string $section): void
    {
        $owner = $this->owner();
        $store = $this->savedPayout($owner);
        $this->fakeBank();
        Http::fake(['api.paystack.co/subaccount/ACCT_existing' => Http::response(['status' => true, 'data' => []])]);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('settings.update'), $this->payoutSettings($section) + ['password' => 'test-password'])
            ->assertSessionHasNoErrors()->assertRedirect(route('settings', ['section' => $section]));

        $this->assertSame('0123456789', $store->fresh()->bankAccount);
        $this->assertSame('CONFIRMED SHOP OWNER', $store->fresh()->accountName);
        Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.paystack.co/subaccount/ACCT_existing'
            && $request['account_number'] === '0123456789');
    }

    #[TestWith(['payments'])]
    #[TestWith(['storefront'])]
    public function test_unchanged_account_can_be_saved_without_password_or_payout_update(string $section): void
    {
        $owner = $this->owner();
        $store = $this->savedPayout($owner);
        $this->fakeBank();
        $data = $this->payoutSettings($section);
        $data['bankAccount'] = '0000000001';

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('settings.update'), $data)->assertSessionHasNoErrors();

        $this->assertSame('OLD OWNER', $store->fresh()->accountName);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/resolve') || str_contains($request->url(), '/subaccount'));
    }

    public function test_changing_only_the_bank_still_requires_password(): void
    {
        $owner = $this->owner();
        $store = $this->savedPayout($owner);
        Http::preventStrayRequests();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('settings.update'), ['section' => 'payments', 'bankCode' => '044', 'bankAccount' => '0000000001'])
            ->assertSessionHasErrors('password');

        $this->assertSame('035', $store->fresh()->bankCode);
        Http::assertNothingSent();
    }

    public function test_repeated_wrong_passwords_temporarily_block_account_replacement(): void
    {
        $owner = $this->owner();
        $store = $this->savedPayout($owner);
        Http::preventStrayRequests();
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('settings.update'), $this->payoutSettings('payments') + ['password' => 'wrong-password'])
                ->assertSessionHasErrors(['password' => 'The login password is incorrect.']);
        }

        $this->post(route('settings.update'), $this->payoutSettings('payments') + ['password' => 'test-password'])
            ->assertSessionHasErrors(['password' => 'Too many password attempts. Please wait a minute and try again.']);

        $this->assertSame('0000000001', $store->fresh()->bankAccount);
        Http::assertNothingSent();
    }

    /** @return array<string, string> */
    private function payoutSettings(string $section): array
    {
        return ['section' => $section, 'bankCode' => '035', 'bankAccount' => '0123456789', 'storeName' => 'Shop',
            'tagline' => '', 'accentColor' => '#16a34a', 'customDomain' => '', 'metaTitle' => '', 'metaDescription' => ''];
    }

    private function savedPayout(User $owner): StorefrontConfig
    {
        $plan = Plan::query()->create(['name' => 'Store plan', 'tier' => 'PROFESSIONAL', 'monthlyPrice' => 1000, 'quarterlyPrice' => 3000, 'annualPrice' => 12000, 'features' => ['Storefront / Online Store'], 'updatedAt' => now()]);
        Subscription::query()->create(['tenantId' => $owner->tenantId, 'planId' => $plan->id, 'status' => 'ACTIVE', 'currentPeriodStart' => now(), 'currentPeriodEnd' => now()->addMonth(), 'updatedAt' => now()]);

        return StorefrontConfig::query()->create(['tenantId' => $owner->tenantId, 'storeName' => 'Shop', 'bankCode' => '035', 'bankName' => 'Wema Bank', 'bankAccount' => '0000000001', 'accountName' => 'OLD OWNER', 'paystackSubaccountCode' => 'ACCT_existing', 'updatedAt' => now()]);
    }

    private function owner(string $provider = 'paystack'): User
    {
        $tenant = Tenant::query()->create(['name' => 'Test shop', 'slug' => 'test-shop', 'email' => 'shop@example.test', 'isActive' => true]);
        $role = Role::query()->create(['tenantId' => $tenant->id, 'name' => 'Owner', 'role' => 'ADMIN', 'permissions' => []]);
        $owner = User::query()->create(['tenantId' => $tenant->id, 'roleId' => $role->id, 'name' => 'Owner', 'email' => $tenant->email, 'passwordHash' => Hash::make('test-password')]);
        PlatformSetting::writePayment(['provider' => $provider, 'platform_split' => 0.04, 'paystack' => ['secret_key' => 'sk_test_example'], 'flutterwave' => ['secret_key' => 'FLWSECK_TEST-example-X']]);

        return $owner;
    }

    private function fakeBank(bool $valid = true): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.paystack.co/bank?*' => Http::response(['status' => true, 'data' => [['code' => '035', 'name' => 'Wema Bank']]]),
            'api.flutterwave.com/v3/banks/NG' => Http::response(['status' => 'success', 'data' => [['code' => '035', 'name' => 'Wema Bank']]]),
            'api.paystack.co/bank/resolve?*' => Http::response(['status' => $valid, 'message' => 'Invalid account', 'data' => $valid ? ['account_name' => 'CONFIRMED SHOP OWNER'] : []], $valid ? 200 : 400),
            'api.flutterwave.com/v3/accounts/resolve' => Http::response(['status' => $valid ? 'success' : 'error', 'message' => 'Invalid account', 'data' => $valid ? ['account_name' => 'CONFIRMED SHOP OWNER'] : []], $valid ? 200 : 400),
        ]);
    }
}
