<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StorefrontConfig;
use App\Models\Tenant;
use App\Services\Flutterwave;
use App\Services\SaleRecorder;
use App\Services\StoreCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(private StoreCart $cart) {}

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

    public function checkoutForm(string $slug): View|RedirectResponse
    {
        [$tenant, $config] = $this->store($slug);

        if ($this->cart->count($tenant->id) < 1) {
            return redirect()
                ->route('store.show', $slug)
                ->with('status', 'Your cart is empty.');
        }

        return $this->storeView('store.checkout', $tenant, $config, [
            'lines' => $this->cart->lines($tenant->id),
            'subtotal' => $this->cart->subtotal($tenant->id),
        ]);
    }

    public function checkout(Request $request, string $slug, SaleRecorder $sales, Flutterwave $flutterwave)
    {
        [$tenant, $config] = $this->store($slug);
        $data = $request->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email'],
            'customerPhone' => ['required', 'string', 'max:50'],
            'customerAddress' => ['nullable', 'string', 'max:500'],
            'method' => ['required', 'in:CARD,TRANSFER'],
        ]);

        $lines = $this->cart->checkoutLines($tenant->id);

        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => 'Add at least one product.']);
        }

        $order = $sales->sell($tenant->id, $lines, [
            'channel' => 'ONLINE',
            'method' => $data['method'] === 'CARD' ? 'CARD' : 'TRANSFER',
            'customerName' => $data['customerName'],
            'customerEmail' => $data['customerEmail'],
            'customerPhone' => $data['customerPhone'],
            'customerAddress' => $data['customerAddress'] ?? null,
            'complete' => false,
        ]);

        $this->cart->clear($tenant->id);

        if ($data['method'] === 'CARD' && $flutterwave->ready()) {
            return redirect()->route('store.pay', ['slug' => $slug, 'ref' => $order->paymentRef]);
        }

        return redirect()->route('store.order', ['slug' => $slug, 'ref' => $order->paymentRef]);
    }

    public function pay(string $slug, string $ref, Flutterwave $flutterwave): View
    {
        [$tenant, $config] = $this->store($slug);
        $order = Order::query()->where('tenantId', $tenant->id)->where('paymentRef', $ref)->firstOrFail();

        return $this->storeView('store.pay', $tenant, $config, [
            'order' => $order,
            'publicKey' => $flutterwave->publicKey(),
            'redirect' => route('store.order', ['slug' => $slug, 'ref' => $ref]),
        ]);
    }

    public function order(string $slug, string $ref, Flutterwave $flutterwave, SaleRecorder $sales): View
    {
        [$tenant, $config] = $this->store($slug);
        $order = Order::query()->where('tenantId', $tenant->id)->where('paymentRef', $ref)->firstOrFail();

        if ($order->status === 'PENDING' && $flutterwave->ready()) {
            try {
                $payment = $flutterwave->verify($ref);
                if ($flutterwave->successful($payment['status'] ?? null)) {
                    $order = $sales->completePending($order, (string) ($payment['id'] ?? $ref));
                }
            } catch (\Throwable) {
                // The customer can refresh after Flutterwave finishes.
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
