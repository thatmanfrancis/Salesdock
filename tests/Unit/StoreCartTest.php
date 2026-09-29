<?php

namespace Tests\Unit;

use App\Services\StoreCart;
use Tests\TestCase;

class StoreCartTest extends TestCase
{
    public function test_it_adds_and_counts_items_per_tenant(): void
    {
        $cart = new StoreCart;

        $cart->add('tenant-a', 'product-1', 2);
        $cart->add('tenant-a', 'product-1', 1);
        $cart->add('tenant-a', 'product-2', 1);
        $cart->add('tenant-b', 'product-1', 5);

        $this->assertSame(4, $cart->count('tenant-a'));
        $this->assertSame(5, $cart->count('tenant-b'));
        $this->assertSame([
            'product-1' => 3,
            'product-2' => 1,
        ], $cart->items('tenant-a'));
    }

    public function test_it_updates_and_removes_items(): void
    {
        $cart = new StoreCart;

        $cart->add('tenant-a', 'product-1', 2);
        $cart->set('tenant-a', 'product-1', 4);
        $this->assertSame(4, $cart->items('tenant-a')['product-1']);

        $cart->set('tenant-a', 'product-1', 0);
        $this->assertSame([], $cart->items('tenant-a'));

        $cart->add('tenant-a', 'product-2', 1);
        $cart->remove('tenant-a', 'product-2');
        $cart->clear('tenant-a');

        $this->assertSame(0, $cart->count('tenant-a'));
    }
}
