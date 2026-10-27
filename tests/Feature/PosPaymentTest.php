<?php

namespace Tests\Feature;

use App\Http\Middleware\ShareWorkspace;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Hold;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Role;
use App\Models\StorefrontConfig;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentManager;
use App\Services\PosPayments;
use App\Services\SaleRecorder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PosPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $path): bool => ! str_contains($path, 'create_branch_stocks_table')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--no-interaction' => true])->assertExitCode(0);
        $this->createBranchStockSchema();
    }

    #[TestWith(['CASH', 'CASH'])]
    #[TestWith(['TRANSFER', 'TRANSFER'])]
    #[TestWith(['CARD', 'PAYSTACK'])]
    #[TestWith(['POS_TERMINAL', 'POS_TERMINAL'])]
    public function test_existing_checkout_completes_without_enabling_new_policies(string $method, string $gateway): void
    {
        [$user, $product] = $this->shop();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->postJson(route('pos.store'), ['qty' => [$product->id => 2], 'method' => $method])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame(8, $product->fresh()->currentStock);
        $this->assertDatabaseHas('transactions', ['gateway' => $gateway, 'method' => $method, 'status' => 'SUCCESS']);
        $this->assertDatabaseCount('sales_ledger', 1);
    }

    public function test_cashless_policy_rejects_direct_cash_submissions(): void
    {
        [$user, $product] = $this->shop(['cashlessOnly' => true]);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->postJson(route('pos.store'), ['qty' => [$product->id => 1], 'method' => 'CASH'])
            ->assertUnprocessable()->assertJsonValidationErrors('method');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->currentStock);
    }

    public function test_verified_checkout_reserves_stock_without_recording_revenue(): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->postJson(route('pos.store'), ['qty' => [$product->id => 2], 'method' => 'CARD'])
            ->assertOk()->assertJsonPath('pending', true)->assertJsonPath('success', false);

        $this->assertDatabaseHas('orders', ['status' => 'PENDING', 'paymentVerificationRequired' => true, 'branchId' => $user->branchId]);
        $this->assertDatabaseHas('transactions', ['status' => 'PENDING', 'completedAt' => null]);
        $this->assertSame(10, $product->fresh()->currentStock);
        $this->assertSame(2, $product->fresh()->reservedQty);
        $this->assertDatabaseCount('sales_ledger', 0);
    }

    public function test_duplicate_checkout_submission_creates_one_sale(): void
    {
        [$user, $product] = $this->shop();
        $payload = ['qty' => [$product->id => 2], 'method' => 'CASH', 'checkoutRequestId' => (string) Str::uuid()];
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user);

        $first = $this->postJson(route('pos.store'), $payload)->assertOk();
        $second = $this->postJson(route('pos.store'), $payload)->assertOk();

        $this->assertSame($first->json('orderId'), $second->json('orderId'));
        $this->assertSame(8, $product->fresh()->currentStock);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('sales_ledger', 1);
    }

    #[TestWith(['RESERVED'])]
    #[TestWith(['COMPLETED'])]
    public function test_verified_orders_cannot_be_completed_or_reserved_manually(string $status): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($user, $product);
        Tenant::query()->whereKey($user->tenantId)->update(['requireVerifiedPayments' => false]);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->postJson(route('orders.status', $order), ['status' => $status])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');

        $this->assertSame('PENDING', $order->fresh()->status);
        $this->assertDatabaseCount('sales_ledger', 0);
    }

    public function test_direct_completion_of_verified_order_is_rejected(): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($user, $product);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->postJson(route('orders.complete', $order))->assertUnprocessable()->assertJsonValidationErrors('payment');

        $this->assertSame(2, $product->fresh()->reservedQty);
    }

    public function test_checkout_reuses_the_same_provider_request_and_collection_account(): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($user, $product);
        Http::preventStrayRequests();
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/example']])]);

        $payments = app(PosPayments::class);
        $first = $payments->start($order);
        $second = $payments->start($order);

        $this->assertSame($first, $second);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['subaccount'] === 'ACCT_shop'
            && $request['amount'] === 21500
            && $request['reference'] === Transaction::query()->first()->metadata['providerReference']);
    }

    public function test_uncertain_initialization_cannot_create_a_second_payment_request(): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($user, $product);
        Http::preventStrayRequests();
        Http::fake(['api.paystack.co/transaction/initialize' => Http::failedConnection()]);
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user);

        $this->post(route('pos.payments.start', $order))->assertSessionHasErrors('payment');
        $this->post(route('pos.payments.start', $order))->assertSessionHasErrors('payment');

        $this->assertSame('UNCERTAIN', Transaction::query()->first()->metadata['checkoutState']);
        $this->assertSame('PENDING', $order->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_verified_webhook_completes_once_and_releases_only_its_reservations(): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($user, $product);
        $this->pending($user, $product);
        $this->prepareAttempt($order);
        Http::preventStrayRequests();
        Http::fake(['api.paystack.co/transaction/123' => Http::response(['status' => true, 'data' => $this->providerPayment($order)])]);

        $this->webhook(['event' => 'charge.success', 'data' => ['id' => 123]])->assertOk();
        $this->webhook(['event' => 'charge.success', 'data' => ['id' => 123]])->assertOk();

        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->currentStock);
        $this->assertSame(2, $product->fresh()->reservedQty);
        $this->assertDatabaseCount('sales_ledger', 1);
        $this->assertDatabaseHas('transactions', ['orderId' => $order->id, 'gatewayRef' => '123', 'status' => 'SUCCESS']);
        Http::assertSentCount(1);
    }

    #[TestWith(['amount', 21400])]
    #[TestWith(['amount', 21600])]
    #[TestWith(['currency', 'USD'])]
    #[TestWith(['reference', 'unrelated'])]
    public function test_mismatched_payment_cannot_complete_sale(string $field, mixed $value): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($user, $product);
        $this->prepareAttempt($order);
        $payment = array_replace($this->providerPayment($order), [$field => $value]);
        Http::preventStrayRequests();
        Http::fake(['api.paystack.co/transaction/123' => Http::response(['status' => true, 'data' => $payment])]);

        $this->webhook(['event' => 'charge.success', 'data' => ['id' => 123]])->assertUnprocessable();

        $this->assertSame('PENDING', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->currentStock);
        $this->assertDatabaseCount('sales_ledger', 0);
        Http::assertSentCount(1);
    }

    public function test_late_payment_is_flagged_without_reviving_cancelled_sale(): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($user, $product);
        $this->prepareAttempt($order);
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->postJson(route('orders.status', $order), ['status' => 'CANCELLED'])->assertRedirect();
        Http::preventStrayRequests();
        Http::fake(['api.paystack.co/transaction/123' => Http::response(['status' => true, 'data' => $this->providerPayment($order)])]);

        $this->webhook(['event' => 'charge.success', 'data' => ['id' => 123]])->assertOk();

        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertSame(0, $product->fresh()->reservedQty);
        $this->assertSame(10, $product->fresh()->currentStock);
        $this->assertTrue(Transaction::query()->first()->metadata['needsReview']);
        $this->assertDatabaseCount('sales_ledger', 0);
        $this->assertDatabaseHas('notifications', ['type' => 'AUDIT_ALERT', 'entityId' => $order->id]);
        Http::assertSentCount(1);
    }

    public function test_payment_reconciliation_recovers_a_missed_webhook(): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($user, $product);
        $this->prepareAttempt($order);
        Http::preventStrayRequests();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => $this->providerPayment($order)])]);

        app(PosPayments::class)->reconcile($order);

        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertSame(0, $product->fresh()->reservedQty);
        Http::assertSentCount(1);
    }

    public function test_confirmed_failure_allows_a_new_attempt_without_releasing_stock(): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($user, $product);
        $this->prepareAttempt($order);
        Http::preventStrayRequests();
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => array_replace($this->providerPayment($order), ['status' => 'failed'])]),
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/retry']]),
        ]);

        app(PosPayments::class)->reconcile($order);
        $this->assertSame('FAILED', Transaction::query()->first()->status);
        $this->assertSame('https://checkout.paystack.com/retry', app(PosPayments::class)->start($order));

        $transaction = Transaction::query()->first();
        $this->assertNotSame($order->paymentRef.'__ATTEMPT', $transaction->metadata['providerReference']);
        $this->assertSame([$order->paymentRef.'__ATTEMPT'], $transaction->metadata['previousReferences']);
        $this->assertSame('PENDING', $order->fresh()->status);
        $this->assertSame(2, $product->fresh()->reservedQty);
        Http::assertSentCount(2);
    }

    public function test_manual_hold_completion_does_not_release_another_sales_reservation(): void
    {
        [$user, $product] = $this->shop();
        $order = $this->pending($user, $product);
        $this->pending($user, $product);
        Hold::query()->create(['tenantId' => $user->tenantId, 'orderId' => $order->id, 'cashierId' => $user->id, 'status' => 'ACTIVE', 'expiresAt' => now()->addHour()]);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->post(route('orders.complete', $order))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, $product->fresh()->reservedQty);
        $this->assertSame(8, $product->fresh()->currentStock);
        $this->assertDatabaseHas('holds', ['orderId' => $order->id, 'status' => 'RESTORED']);
    }

    public function test_storefront_payment_still_completes_with_the_existing_gateway_flow(): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = app(SaleRecorder::class)->sell($user->tenantId, [['productId' => $product->id, 'qty' => 2]], [
            'method' => 'CARD', 'complete' => false, 'channel' => 'ONLINE', 'branchId' => $user->branchId,
        ]);

        $result = app(BillingService::class)->settlePayment([
            'id' => '123', 'tx_ref' => $order->paymentRef.'__ATTEMPT', 'amount' => 215, 'status' => 'success', 'currency' => 'NGN',
        ], app(PaymentManager::class)->driver('paystack'), app(SaleRecorder::class));

        $this->assertSame('order', $result);
        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertSame(0, $product->fresh()->reservedQty);
        $this->assertDatabaseCount('sales_ledger', 1);
    }

    public function test_reserved_storefront_order_completes_without_deducting_stock_twice(): void
    {
        [$user, $product] = $this->shop();
        $order = app(SaleRecorder::class)->sell($user->tenantId, [['productId' => $product->id, 'qty' => 2]], [
            'method' => 'CARD', 'complete' => false, 'channel' => 'ONLINE', 'branchId' => $user->branchId,
        ]);
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->postJson(route('orders.status', $order), ['status' => 'RESERVED'])->assertRedirect();
        $this->assertSame(8, $product->fresh()->currentStock);

        app(BillingService::class)->settlePayment([
            'id' => '123', 'tx_ref' => $order->paymentRef.'__ATTEMPT', 'amount' => 215, 'status' => 'success', 'currency' => 'NGN',
        ], app(PaymentManager::class)->driver('paystack'), app(SaleRecorder::class));

        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->currentStock);
        $this->assertSame(0, $product->fresh()->reservedQty);
        $this->assertDatabaseCount('sales_ledger', 1);
    }

    public function test_branch_cannot_sell_stock_reserved_for_another_pending_sale_at_that_branch(): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $otherBranch = Branch::query()->create(['tenantId' => $user->tenantId, 'name' => 'Lekki']);
        BranchStock::query()->create(['tenantId' => $user->tenantId, 'productId' => $product->id, 'branchId' => $user->branchId, 'quantity' => 3]);
        BranchStock::query()->create(['tenantId' => $user->tenantId, 'productId' => $product->id, 'branchId' => $otherBranch->id, 'quantity' => 7]);
        $this->pending($user, $product);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->postJson(route('pos.store'), ['qty' => [$product->id => 2], 'method' => 'CARD'])
            ->assertUnprocessable()->assertJsonValidationErrors('cart');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(10, $product->fresh()->currentStock);
    }

    public function test_reconciliation_command_recovers_a_payment_without_webhook(): void
    {
        $this->freezeTime();
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($user, $product);
        $this->prepareAttempt($order);
        $this->travel(2)->minutes();
        Http::preventStrayRequests();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => $this->providerPayment($order)])]);

        $this->artisan('payments:reconcile-pos')->assertSuccessful();

        $this->assertSame('COMPLETED', $order->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_provider_payment_method_is_recorded_for_reconciliation(): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($user, $product);
        $this->prepareAttempt($order);
        Http::preventStrayRequests();
        Http::fake(['api.paystack.co/transaction/123' => Http::response(['status' => true, 'data' => $this->providerPayment($order) + ['channel' => 'bank_transfer']])]);

        $this->webhook(['event' => 'charge.success', 'data' => ['id' => 123]])->assertOk();

        $this->assertDatabaseHas('transactions', ['orderId' => $order->id, 'method' => 'TRANSFER']);
        $this->assertDatabaseHas('sales_ledger', ['orderId' => $order->id, 'paymentMethod' => 'TRANSFER']);
        Http::assertSentCount(1);
    }

    public function test_other_tenants_cannot_view_or_refresh_payment(): void
    {
        [$owner, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($owner, $product);
        [$other] = $this->shop();
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($other);

        $this->getJson(route('pos.payments.status', $order))->assertNotFound();
        $this->post(route('pos.payments.refresh', $order))->assertNotFound();
    }

    public function test_guests_cannot_access_payment_status(): void
    {
        [$owner, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($owner, $product);

        $this->get(route('pos.payments.show', $order))->assertRedirect(route('login'));
    }

    public function test_unsigned_webhook_cannot_complete_payment(): void
    {
        Http::preventStrayRequests();

        $this->postJson(route('billing.webhook.paystack'), ['event' => 'charge.success', 'data' => ['id' => 123]])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_refund_notification_cannot_be_treated_as_a_successful_charge(): void
    {
        [$user, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($user, $product);
        Http::preventStrayRequests();

        $this->webhook(['event' => 'refund.processed', 'data' => ['id' => 123]])->assertOk();

        $this->assertSame('PENDING', $order->fresh()->status);
        $this->assertDatabaseCount('sales_ledger', 0);
        Http::assertNothingSent();
    }

    public function test_cashier_cannot_access_another_cashiers_payment_even_with_order_permission(): void
    {
        [$owner, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($owner, $product);
        $role = Role::query()->create(['tenantId' => $owner->tenantId, 'name' => 'Cashier', 'role' => 'CASHIER', 'permissions' => ['canUsePOS' => true, 'canManageOrders' => true]]);
        $cashier = User::query()->create(['tenantId' => $owner->tenantId, 'roleId' => $role->id, 'name' => 'Cashier', 'email' => 'cashier@example.test']);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($cashier)
            ->getJson(route('pos.payments.status', $order))->assertNotFound();
    }

    public function test_flutterwave_verified_checkout_completes_through_its_existing_webhook(): void
    {
        [$owner, $product] = $this->shop(['requireVerifiedPayments' => true]);
        PlatformSetting::writePayment(['provider' => 'flutterwave', 'platform_split' => 0, 'flutterwave' => ['secret_key' => 'FLWSECK_TEST-example-X', 'secret_hash' => 'test-hash']]);
        $store = StorefrontConfig::query()->where('tenantId', $owner->tenantId)->first();
        $store->forceFill(['flutterwaveSubaccountId' => 'RS_shop', 'updatedAt' => now()])->save();
        $order = $this->pending($owner, $product);
        Http::preventStrayRequests();
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/example']]),
            'api.flutterwave.com/v3/transactions/123/verify' => Http::response(['status' => 'success', 'data' => [
                'id' => 123, 'tx_ref' => $order->paymentRef.'__ATTEMPT', 'amount' => 215,
                'currency' => 'NGN', 'status' => 'successful', 'payment_type' => 'card',
            ]]),
        ]);

        $this->assertSame('https://checkout.flutterwave.com/example', app(PosPayments::class)->start($order));
        $this->prepareAttempt($order);
        $this->postJson(route('billing.webhook'), ['event' => 'charge.completed', 'data' => ['id' => 123]], ['verif-hash' => 'test-hash'])->assertOk();

        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->currentStock);
        $this->assertDatabaseHas('transactions', ['orderId' => $order->id, 'gateway' => 'FLUTTERWAVE', 'status' => 'SUCCESS']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.flutterwave.com/v3/payments' && $request['subaccounts'] === [['id' => 'RS_shop']]);
    }

    public function test_cashier_cannot_change_checkout_policy(): void
    {
        [$owner] = $this->shop();
        $role = Role::query()->create(['tenantId' => $owner->tenantId, 'name' => 'Cashier', 'role' => 'CASHIER', 'permissions' => []]);
        $owner->forceFill(['roleId' => $role->id])->save();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('settings.update'), ['section' => 'payments', 'cashlessOnly' => 1])->assertForbidden();

        $this->assertFalse($owner->tenant->cashlessOnly);
    }

    public function test_owner_can_enable_policy_without_affecting_other_shops(): void
    {
        [$owner] = $this->shop();
        [$other] = $this->shop();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('settings.update'), ['section' => 'payments', 'cashlessOnly' => 1, 'requireVerifiedPayments' => 1])
            ->assertSessionHasNoErrors()->assertRedirect(route('settings', ['section' => 'payments']));

        $this->assertTrue($owner->tenant->requireVerifiedPayments);
        $this->assertFalse($other->tenant->requireVerifiedPayments);
    }

    public function test_stock_snapshot_tracks_branch_reservations(): void
    {
        [$owner, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $this->pending($owner, $product);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->getJson(route('pos.stock'))->assertOk()->assertJsonPath('stock.'.$product->id, 8);
    }

    public function test_owner_can_choose_payment_methods_without_changing_another_shop(): void
    {
        [$owner] = $this->shop();
        [$other] = $this->shop();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('settings.update'), ['section' => 'payments', 'paymentMethodsPresent' => 1, 'posPaymentMethods' => ['TRANSFER']])
            ->assertSessionHasNoErrors()->assertRedirect(route('settings', ['section' => 'payments']));

        $this->assertSame(['TRANSFER'], $owner->tenant->posPaymentMethods);
        $this->assertNull($other->tenant->posPaymentMethods);
        $response = $this->get(route('pos'))->assertOk()
            ->assertSee('data-method="TRANSFER"', false)->assertSee('value="TRANSFER" id="pos-method"', false);
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $buttons = (new \DOMXPath($document))->query('//div[@class="pos-pay"]/button/@data-method');
        $this->assertSame(['TRANSFER'], array_map(fn ($attribute): string => $attribute->nodeValue, iterator_to_array($buttons)));
        $this->getJson(route('pos.stock'))->assertJsonPath('paymentMethods', ['TRANSFER' => 'Transfer']);
    }

    #[TestWith(['CASH'])]
    #[TestWith(['CARD'])]
    #[TestWith(['POS_TERMINAL'])]
    public function test_disabled_methods_cannot_be_submitted_directly(string $method): void
    {
        [$owner, $product] = $this->shop(['posPaymentMethods' => ['TRANSFER']]);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->postJson(route('pos.store'), ['qty' => [$product->id => 1], 'method' => $method])
            ->assertUnprocessable()->assertJsonValidationErrors('method');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->currentStock);
    }

    #[TestWith([])]
    #[TestWith(['CASH', 'CARD', 'CHEQUE'])]
    public function test_payment_settings_require_valid_methods(string ...$methods): void
    {
        [$owner] = $this->shop();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('settings.update'), ['section' => 'payments', 'paymentMethodsPresent' => 1, 'posPaymentMethods' => $methods])
            ->assertSessionHasErrors();

        $this->assertNull($owner->tenant->posPaymentMethods);
    }

    public function test_cashless_settings_cannot_leave_only_cash_enabled(): void
    {
        [$owner] = $this->shop();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('settings.update'), ['section' => 'payments', 'cashlessOnly' => 1, 'paymentMethodsPresent' => 1, 'posPaymentMethods' => ['CASH']])
            ->assertSessionHasErrors('posPaymentMethods');

        $this->assertFalse($owner->tenant->cashlessOnly);
    }

    public function test_held_sale_cannot_complete_after_its_payment_method_is_disabled(): void
    {
        [$owner, $product] = $this->shop();
        $order = $this->pending($owner, $product);
        Tenant::query()->whereKey($owner->tenantId)->update(['posPaymentMethods' => json_encode(['TRANSFER'])]);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->postJson(route('orders.complete', $order))->assertUnprocessable()->assertJsonValidationErrors('payment');

        $this->assertSame('PENDING', $order->fresh()->status);
        $this->assertSame(2, $product->fresh()->reservedQty);
    }

    public function test_in_flight_verified_payment_can_settle_after_its_method_is_disabled(): void
    {
        [$owner, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($owner, $product);
        $this->prepareAttempt($order);
        Tenant::query()->whereKey($owner->tenantId)->update(['posPaymentMethods' => json_encode(['TRANSFER'])]);
        Http::preventStrayRequests();
        Http::fake(['api.paystack.co/transaction/123' => Http::response(['status' => true, 'data' => $this->providerPayment($order)])]);

        $this->webhook(['event' => 'charge.success', 'data' => ['id' => 123]])->assertOk();

        $this->assertSame('COMPLETED', $order->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_payment_and_settings_screens_render(): void
    {
        [$owner, $product] = $this->shop(['requireVerifiedPayments' => true]);
        $order = $this->pending($owner, $product);
        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner);
        $product->forceFill(['barcode' => '001234567890'])->save();
        Http::preventStrayRequests();
        Http::fake(['api.paystack.co/bank*' => Http::response(['status' => true, 'data' => []])]);

        $this->get(route('pos.payments.show', $order))->assertOk()->assertSee('Stock is set aside');
        $this->get(route('settings', ['section' => 'payments']))->assertOk()->assertSee('Require payment provider confirmation');
        $this->get(route('pos'))->assertOk()->assertSee('Continue to payment')->assertSee('data-barcode="001234567890"', false);

        Http::assertSentCount(1);
    }

    /** @return array{User, Product} */
    private function shop(array $policy = []): array
    {
        $slug = 'shop-'.Str::lower(Str::random(10));
        $tenant = Tenant::query()->create(['name' => 'Test shop', 'slug' => $slug, 'email' => $slug.'@example.test'] + $policy);
        $branch = Branch::query()->create(['tenantId' => $tenant->id, 'name' => 'Ikeja', 'isMain' => true]);
        $role = Role::query()->create(['tenantId' => $tenant->id, 'name' => 'Owner', 'role' => 'ADMIN', 'permissions' => []]);
        $user = User::query()->create(['tenantId' => $tenant->id, 'branchId' => $branch->id, 'roleId' => $role->id, 'name' => 'Owner', 'email' => $tenant->email]);
        $product = Product::query()->create(['tenantId' => $tenant->id, 'name' => 'Milk', 'sku' => 'MILK', 'price' => 100, 'costPrice' => 50, 'currentStock' => 10]);
        PlatformSetting::writePayment(['provider' => 'paystack', 'platform_split' => 0, 'paystack' => ['secret_key' => 'sk_test_example']]);
        StorefrontConfig::query()->create(['tenantId' => $tenant->id, 'storeName' => 'Test shop', 'paystackSubaccountCode' => 'ACCT_shop', 'updatedAt' => now()]);

        return [$user, $product];
    }

    private function pending(User $user, Product $product): Order
    {
        return app(SaleRecorder::class)->sell($user->tenantId, [['productId' => $product->id, 'qty' => 2]], [
            'method' => 'CARD', 'complete' => false, 'branchId' => $user->branchId, 'cashierId' => $user->id,
        ]);
    }

    private function prepareAttempt(Order $order): void
    {
        $transaction = Transaction::query()->where('orderId', $order->id)->firstOrFail();
        $transaction->forceFill(['metadata' => array_replace($transaction->metadata ?? [], ['providerReference' => $order->paymentRef.'__ATTEMPT'])])->save();
    }

    private function providerPayment(Order $order): array
    {
        return ['id' => 123, 'reference' => $order->paymentRef.'__ATTEMPT', 'status' => 'success', 'amount' => 21500, 'currency' => 'NGN'];
    }

    private function webhook(array $payload): TestResponse
    {
        $body = json_encode($payload);

        return $this->call('POST', route('billing.webhook.paystack'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_example'),
        ], $body);
    }

    /**
     * SQLite fixture for the branch migration that also alters PostgreSQL enum types.
     */
    private function createBranchStockSchema(): void
    {
        Schema::create('branch_stocks', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('branchId');
            $table->string('productId');
            $table->integer('quantity')->default(0);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
            $table->unique(['branchId', 'productId']);
            $table->foreign('tenantId')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('branchId')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('productId')->references('id')->on('products')->cascadeOnDelete();
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->string('branchId')->nullable();
        });
    }
}
