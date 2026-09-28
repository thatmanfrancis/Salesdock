@extends('layouts.store')

@section('title', ($config->metaTitle ?: $config->storeName).' | SalesDock')

@section('content')
    <form method="post" action="{{ route('store.checkout', $tenant->slug) }}">
        @csrf
        <div class="grid">
            @forelse ($products as $product)
                <article>
                    <strong><a href="{{ route('store.product', [$tenant->slug, $product]) }}">{{ $product->name }}</a></strong>
                    <span>₦{{ number_format($product->price, 2) }}</span>
                    <label>Qty<input type="number" min="0" name="qty[{{ $product->id }}]" value="0"></label>
                </article>
            @empty
                <p>No products are listed yet.</p>
            @endforelse
        </div>
        <label>Name<input name="customerName" required></label>
        <label>Email<input type="email" name="customerEmail" required></label>
        <label>Phone<input name="customerPhone" required></label>
        <label>Address<textarea name="customerAddress"></textarea></label>
        <label>Payment
            <select name="method">
                <option value="CARD">Card</option>
                <option value="TRANSFER">Bank transfer</option>
            </select>
        </label>
        <button>Place order</button>
    </form>
@endsection
