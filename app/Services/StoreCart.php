<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class StoreCart
{
    public function items(string $tenantId): array
    {
        /** @var array<string, int> $cart */
        $cart = session($this->key($tenantId), []);

        return array_map('intval', $cart);
    }

    public function count(string $tenantId): int
    {
        return (int) array_sum($this->items($tenantId));
    }

    public function add(string $tenantId, string $productId, int $qty = 1): void
    {
        $qty = max(1, $qty);
        $cart = $this->items($tenantId);
        $cart[$productId] = ($cart[$productId] ?? 0) + $qty;
        $this->put($tenantId, $cart);
    }

    public function set(string $tenantId, string $productId, int $qty): void
    {
        $cart = $this->items($tenantId);

        if ($qty < 1) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $qty;
        }

        $this->put($tenantId, $cart);
    }

    public function remove(string $tenantId, string $productId): void
    {
        $cart = $this->items($tenantId);
        unset($cart[$productId]);
        $this->put($tenantId, $cart);
    }

    public function clear(string $tenantId): void
    {
        session()->forget($this->key($tenantId));
    }

    /**
     * @return Collection<int, array{product: Product, qty: int, lineTotal: float}>
     */
    public function lines(string $tenantId): Collection
    {
        $cart = $this->items($tenantId);

        if ($cart === []) {
            return collect();
        }

        $products = Product::query()
            ->where('tenantId', $tenantId)
            ->where('isActive', true)
            ->whereIn('availabilityMode', ['BOTH', 'ONLINE_ONLY'])
            ->whereIn('id', array_keys($cart))
            ->get()
            ->keyBy('id');

        $lines = collect();

        foreach ($cart as $productId => $qty) {
            $product = $products->get($productId);

            if (! $product || $qty < 1) {
                continue;
            }

            $lines->push([
                'product' => $product,
                'qty' => $qty,
                'lineTotal' => (float) $product->price * $qty,
            ]);
        }

        return $lines;
    }

    public function subtotal(string $tenantId): float
    {
        return (float) $this->lines($tenantId)->sum('lineTotal');
    }

    /**
     * @return list<array{productId: string, qty: int}>
     */
    public function checkoutLines(string $tenantId): array
    {
        return $this->lines($tenantId)
            ->map(fn (array $line): array => [
                'productId' => $line['product']->id,
                'qty' => $line['qty'],
            ])
            ->values()
            ->all();
    }

    private function key(string $tenantId): string
    {
        return "store_cart.{$tenantId}";
    }

    /**
     * @param  array<string, int>  $cart
     */
    private function put(string $tenantId, array $cart): void
    {
        session([$this->key($tenantId) => $cart]);
    }
}
