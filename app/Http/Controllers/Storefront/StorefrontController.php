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
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function show(string $slug)
    {
        [$tenant, $config] = $this->store($slug);

        return view('store.show', [
            'tenant' => $tenant,
            'config' => $config,
            'products' => Product::query()
                ->where('tenantId', $tenant->id)
                ->where('isActive', true)
                ->whereIn('availabilityMode', ['BOTH', 'ONLINE_ONLY'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function product(string $slug, Product $product)
    {
        [$tenant, $config] = $this->store($slug);
        abort_unless($product->tenantId === $tenant->id && $product->isActive, 404);

        return view('store.product', compact('tenant', 'config', 'product'));
    }

    public function checkout(Request $request, string $slug, SaleRecorder $sales, Flutterwave $flutterwave)
    {
        [$tenant, $config] = $this->store($slug);
        $data = $request->validate([
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'integer', 'min:0'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email'],
            'customerPhone' => ['required', 'string', 'max:50'],
            'customerAddress' => ['nullable', 'string', 'max:500'],
            'method' => ['required', 'in:CARD,TRANSFER'],
        ]);

        $lines = [];
        foreach ($data['qty'] as $productId => $qty) {
            if ((int) $qty > 0) {
                $lines[] = ['productId' => $productId, 'qty' => (int) $qty];
            }
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

        if ($data['method'] === 'CARD' && $flutterwave->ready()) {
            return redirect()->route('store.pay', ['slug' => $slug, 'ref' => $order->paymentRef]);
        }

        return redirect()->route('store.order', ['slug' => $slug, 'ref' => $order->paymentRef]);
    }

    public function pay(string $slug, string $ref, Flutterwave $flutterwave)
    {
        [$tenant, $config] = $this->store($slug);
        $order = Order::query()->where('tenantId', $tenant->id)->where('paymentRef', $ref)->firstOrFail();

        return view('store.pay', [
            'tenant' => $tenant,
            'config' => $config,
            'order' => $order,
            'publicKey' => $flutterwave->publicKey(),
            'redirect' => route('store.order', ['slug' => $slug, 'ref' => $ref]),
        ]);
    }

    public function order(string $slug, string $ref, Flutterwave $flutterwave, SaleRecorder $sales)
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

        return view('store.order', [
            'tenant' => $tenant,
            'config' => $config,
            'order' => $order,
            'items' => OrderItem::query()->where('orderId', $order->id)->get(),
        ]);
    }

    private function store(string $slug): array
    {
        $tenant = Tenant::query()->where('slug', $slug)->where('isActive', true)->firstOrFail();
        $config = StorefrontConfig::query()->where('tenantId', $tenant->id)->where('isPublic', true)->firstOrFail();

        return [$tenant, $config];
    }
}
