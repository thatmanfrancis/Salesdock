<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\StorefrontConfig;
use App\Models\Tenant;
use App\Services\BillingService;
use App\Services\DeliveryBooking;
use App\Services\FezDelivery;
use App\Services\PaymentManager;
use App\Services\SaleRecorder;
use App\Services\StoreCart;
use App\Support\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(private StoreCart $cart) {}

    private function serviceRate(): float
    {
        return max(0.0, min(0.5, (float) PlatformSetting::payment()['platform_split']));
    }

    public function show(Request $request, string $slug): View
    {
        [$tenant, $config] = $this->store($slug);

        $query = Product::query()
            ->where('tenantId', $tenant->id)
            ->where('isActive', true)
            ->whereIn('availabilityMode', ['BOTH', 'ONLINE_ONLY']);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%');
            });
        }

        $category = trim((string) $request->query('category', ''));
        if ($category !== '') {
            $query->where('category', $category);
        }

        $products = $query->orderBy('name')->get();

        $categories = Product::query()
            ->where('tenantId', $tenant->id)
            ->where('isActive', true)
            ->whereIn('availabilityMode', ['BOTH', 'ONLINE_ONLY'])
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return $this->storeView('store.show', $tenant, $config, [
            'products' => $products,
            'categories' => $categories,
            'search' => $search,
            'category' => $category,
        ]);
    }

    public function product(string $slug, Product $product): View
    {
        [$tenant, $config] = $this->store($slug);
        abort_unless(
            $product->tenantId === $tenant->id
            && $product->isActive
            && in_array($product->availabilityMode, ['BOTH', 'ONLINE_ONLY'], true),
            404
        );

        return $this->storeView('store.product', $tenant, $config, [
            'product' => $product,
            'available' => $this->availableQty($product),
        ]);
    }

    public function cart(string $slug): View
    {
        [$tenant, $config] = $this->store($slug);

        return $this->storeView('store.cart', $tenant, $config, [
            'lines' => $this->cart->lines($tenant->id),
            'subtotal' => $this->cart->subtotal($tenant->id),
        ]);
    }

    public function addToCart(Request $request, string $slug)
    {
        [$tenant] = $this->store($slug);
        $data = $request->validate([
            'productId' => ['required', 'string'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $product = $this->sellableProduct($tenant->id, $data['productId']);
        $qty = (int) ($data['qty'] ?? 1);
        $available = $this->availableQty($product);
        $already = $this->cart->items($tenant->id)[$product->id] ?? 0;

        if ($available < 1) {
            throw ValidationException::withMessages(['cart' => "{$product->name} is out of stock."]);
        }

        if ($already + $qty > $available) {
            throw ValidationException::withMessages([
                'cart' => "{$product->name} only has {$available} available.",
            ]);
        }

        $this->cart->add($tenant->id, $product->id, $qty);
        $productQty = $this->cart->items($tenant->id)[$product->id] ?? 0;
        $message = "{$product->name} added to cart.";

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'cartCount' => $this->cart->count($tenant->id),
                'productId' => $product->id,
                'productQty' => $productQty,
            ]);
        }

        return redirect()
            ->back(fallback: route('store.show', $slug))
            ->with('status', $message);
    }

    public function updateCart(Request $request, string $slug)
    {
        [$tenant] = $this->store($slug);
        $data = $request->validate([
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        foreach ($data['qty'] as $productId => $qty) {
            $product = Product::query()
                ->where('tenantId', $tenant->id)
                ->whereKey($productId)
                ->first();

            if (! $product) {
                $this->cart->remove($tenant->id, (string) $productId);

                continue;
            }

            $qty = (int) $qty;
            $available = $this->availableQty($product);

            if ($qty > $available) {
                throw ValidationException::withMessages([
                    'cart' => "{$product->name} only has {$available} available.",
                ]);
            }

            $this->cart->set($tenant->id, $product->id, $qty);
        }

        return redirect()
            ->route('store.cart', $slug)
            ->with('status', 'Cart updated.');
    }

    public function removeFromCart(Request $request, string $slug)
    {
        [$tenant] = $this->store($slug);
        $data = $request->validate([
            'productId' => ['required', 'string'],
        ]);

        $this->cart->remove($tenant->id, $data['productId']);

        return redirect()
            ->route('store.cart', $slug)
            ->with('status', 'Item removed.');
    }

    public function deliveryQuote(Request $request, string $slug, FezDelivery $fez, DeliveryBooking $booking)
    {
        [$tenant] = $this->store($slug);
        $data = $request->validate([
            'state' => ['required', 'string', 'in:'.implode(',', FezDelivery::states())],
        ]);

        $account = $fez->accountFor($tenant->id);
        if (! $account) {
            return response()->json(['ok' => false, 'message' => 'Fez delivery is not available for this shop.'], 422);
        }

        $lines = $this->cart->lines($tenant->id);
        if ($lines->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Your cart is empty.'], 422);
        }

        try {
            $weight = $booking->cartWeightKg($tenant->id, $lines, $account);
            $quote = $fez->quote($account, $data['state'], $account->pickupState, $weight);
        } catch (\Throwable $e) {
            Log::warning('Fez quote failed', ['tenantId' => $tenant->id, 'error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => 'We could not get a Fez delivery price right now. Choose pickup or try again.'], 422);
        }

        return response()->json([
            'ok' => true,
            'fee' => $quote['totalCost'],
            'weightKg' => $weight,
            'message' => 'Fez delivery to '.$data['state'],
        ]);
    }

    public function checkoutForm(string $slug): View|RedirectResponse
    {
        [$tenant, $config] = $this->store($slug);

        if ($this->cart->count($tenant->id) < 1) {
            return redirect()
                ->route('store.show', $slug)
                ->with('status', 'Your cart is empty.');
        }

        $deliveryAccount = app(FezDelivery::class)->accountFor($tenant->id);

        return $this->storeView('store.checkout', $tenant, $config, [
            'lines' => $this->cart->lines($tenant->id),
            'subtotal' => $this->cart->subtotal($tenant->id),
            'serviceRate' => $this->serviceRate(),
            'gatewayLabel' => app(PaymentManager::class)->active()->label(),
            'deliveryAccount' => $deliveryAccount,
            'deliveryStates' => FezDelivery::states(),
        ]);
    }

    public function checkout(Request $request, string $slug, SaleRecorder $sales, PaymentManager $payments, FezDelivery $fez, DeliveryBooking $booking)
    {
        [$tenant, $config] = $this->store($slug);
        Turnstile::verify($request, 'checkout');
        $data = $request->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email'],
            'customerPhone' => ['required', 'string', 'max:50'],
            'customerAddress' => ['nullable', 'string', 'max:500'],
            'deliveryMethod' => ['nullable', 'in:PICKUP,FEZ'],
            'deliveryState' => ['nullable', 'string', 'in:'.implode(',', FezDelivery::states())],
            'deliveryNotes' => ['nullable', 'string', 'max:500'],
            'recipientAlternatePhone' => ['nullable', 'string', 'max:50'],
            'method' => ['required', 'in:CARD,TRANSFER'],
        ]);

        $lines = $this->cart->checkoutLines($tenant->id);

        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => 'Add at least one product.']);
        }

        $deliveryMethod = (string) ($data['deliveryMethod'] ?? 'PICKUP');
        $deliveryAccount = null;
        $deliveryFee = 0.0;
        $deliveryWeight = 0.0;

        if ($deliveryMethod === 'FEZ') {
            if (empty($data['deliveryState']) || empty($data['customerAddress'])) {
                throw ValidationException::withMessages(['deliveryState' => 'Choose the delivery state and enter the full delivery address for Fez delivery.']);
            }

            $deliveryAccount = $fez->accountFor($tenant->id);
            if (! $deliveryAccount) {
                throw ValidationException::withMessages(['deliveryMethod' => 'Fez delivery is not available for this shop. Choose pickup instead.']);
            }

            try {
                $cartLines = $this->cart->lines($tenant->id);
                $deliveryWeight = $booking->cartWeightKg($tenant->id, $cartLines, $deliveryAccount);
                $quote = $fez->quote($deliveryAccount, $data['deliveryState'], $deliveryAccount->pickupState, $deliveryWeight);
                $deliveryFee = (float) $quote['totalCost'];
            } catch (\Throwable $e) {
                Log::warning('Fez checkout quote failed', ['tenantId' => $tenant->id, 'error' => $e->getMessage()]);
                throw ValidationException::withMessages(['deliveryMethod' => 'We could not get a Fez delivery price right now. Choose pickup or try again.']);
            }
        }

        $gateway = $payments->active();
        $order = $sales->sell($tenant->id, $lines, [
            'channel' => 'ONLINE',
            'method' => $data['method'] === 'CARD' ? 'CARD' : 'TRANSFER',
            'customerName' => $data['customerName'],
            'customerEmail' => $data['customerEmail'],
            'customerPhone' => $data['customerPhone'],
            'customerAddress' => $data['customerAddress'] ?? null,
            'deliveryMethod' => $deliveryMethod,
            'deliveryState' => $deliveryMethod === 'FEZ' ? $data['deliveryState'] : null,
            'deliveryFee' => $deliveryFee,
            'serviceRate' => $data['method'] === 'CARD' ? $this->serviceRate() : 0,
            'complete' => false,
        ]);

        if ($deliveryMethod === 'FEZ' && $deliveryAccount) {
            $booking->createForOrder($order, $deliveryAccount, [
                'name' => $data['customerName'],
                'email' => $data['customerEmail'],
                'phone' => $data['customerPhone'],
                'alternatePhone' => $data['recipientAlternatePhone'] ?? null,
                'address' => $data['customerAddress'],
                'state' => $data['deliveryState'],
                'notes' => $data['deliveryNotes'] ?? null,
            ], $booking->pickupSnapshot($deliveryAccount, $tenant), $deliveryFee, $deliveryWeight);
        }

        $this->cart->clear($tenant->id);

        if ($data['method'] === 'CARD' && $gateway->ready()) {
            return redirect()->route('store.pay', ['slug' => $slug, 'ref' => $order->paymentRef]);
        }

        return redirect()->route('store.order', ['slug' => $slug, 'ref' => $order->paymentRef]);
    }

    public function pay(string $slug, string $ref, PaymentManager $payments): View
    {
        [$tenant, $config] = $this->store($slug);
        $order = Order::query()->where('tenantId', $tenant->id)->where('paymentRef', $ref)->firstOrFail();
        $gateway = $payments->active();

        return $this->storeView('store.pay', $tenant, $config, [
            'order' => $order,
            'ready' => $gateway->ready() && $order->status === 'PENDING',
            'gatewayLabel' => $gateway->label(),
        ]);
    }

    public function startPayment(string $slug, string $ref, PaymentManager $payments): RedirectResponse
    {
        [$tenant, $config] = $this->store($slug);
        $order = Order::query()->where('tenantId', $tenant->id)->where('paymentRef', $ref)->where('status', 'PENDING')->firstOrFail();
        $gateway = $payments->active();
        $column = $gateway->subaccountColumn();

        try {
            if (! $config->{$column}) {
                return back()->withErrors(['payment' => 'This shop has not finished setting up payouts yet. Please pay by bank transfer, or ask the shop to add their bank account in Settings.']);
            }

            $link = $gateway->checkoutLink([
                'reference' => $order->paymentRef,
                'amount' => (float) $order->netAmount,
                'email' => (string) $order->customerEmail,
                'name' => $order->customerName,
                'phone' => $order->customerPhone,
                'redirect' => route('store.order', ['slug' => $slug, 'ref' => $ref]),
                'title' => $config->storeName,
                'description' => 'Order '.$order->paymentRef,
                'subaccountId' => $config->{$column},
            ]);
        } catch (\Throwable $e) {
            Log::warning($gateway->label().' checkout link failed', ['order' => $order->paymentRef, 'error' => $e->getMessage()]);

            return back()->withErrors(['payment' => 'We could not open the payment page: '.$e->getMessage()]);
        }

        return redirect()->away($link);
    }

    public function order(Request $request, string $slug, string $ref, PaymentManager $payments, SaleRecorder $sales, BillingService $billing): View
    {
        [$tenant, $config] = $this->store($slug);
        $order = Order::query()->where('tenantId', $tenant->id)->where('paymentRef', $ref)->firstOrFail();
        $gateway = $payments->active();

        if ($order->status === 'PENDING' && $gateway->ready() && ! in_array($request->query('status'), ['cancelled', 'failed'], true)) {
            try {
                $transactionId = (string) $request->query('transaction_id', '');
                $payment = $transactionId !== ''
                    ? $gateway->verifyTransaction($transactionId)
                    : $gateway->verifyByReference((string) $request->query('tx_ref', $request->query('reference', $request->query('trxref', $ref))));
                if ($gateway->baseReference((string) ($payment['tx_ref'] ?? '')) === $ref) {
                    $billing->settlePayment($payment, $gateway, $sales);
                    $order->refresh();
                }
            } catch (\Throwable) {
                // Not paid yet, or the gateway is still processing; the webhook settles it when it lands.
            }
        }

        $items = OrderItem::query()->where('orderId', $order->id)->get();
        $products = Product::query()
            ->whereIn('id', $items->pluck('productId'))
            ->get()
            ->keyBy('id');

        return $this->storeView('store.order', $tenant, $config, [
            'order' => $order,
            'items' => $items,
            'products' => $products,
            'delivery' => Delivery::query()->where('orderId', $order->id)->first(),
        ]);
    }

    private function storeView(string $view, Tenant $tenant, StorefrontConfig $config, array $data = []): View
    {
        return view($view, array_merge($data, [
            'tenant' => $tenant,
            'config' => $config,
            'cartCount' => $this->cart->count($tenant->id),
            'cartSubtotal' => $this->cart->subtotal($tenant->id),
            'cartItems' => $this->cart->items($tenant->id),
        ]));
    }

    private function sellableProduct(string $tenantId, string $productId): Product
    {
        $product = Product::query()
            ->where('tenantId', $tenantId)
            ->whereKey($productId)
            ->where('isActive', true)
            ->whereIn('availabilityMode', ['BOTH', 'ONLINE_ONLY'])
            ->firstOrFail();

        return $product;
    }

    private function availableQty(Product $product): int
    {
        return max(0, (int) $product->currentStock - (int) $product->reservedQty);
    }

    /**
     * @return array{0: Tenant, 1: StorefrontConfig}
     */
    private function store(string $slug): array
    {
        $tenant = Tenant::query()->where('slug', $slug)->where('isActive', true)->firstOrFail();
        $config = StorefrontConfig::query()->where('tenantId', $tenant->id)->where('isPublic', true)->firstOrFail();

        return [$tenant, $config];
    }
}
