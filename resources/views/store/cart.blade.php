@extends('layouts.store')

@section('title', 'Cart | '.$config->storeName)

@section('content')
    <section class="sf-panel">
        <header class="sf-panel-head">
            <h1>Your cart</h1>
            <a href="{{ route('store.show', $tenant->slug) }}">Continue shopping</a>
        </header>

        @if ($lines->isEmpty())
            <p class="sf-empty">Your cart is empty.</p>
            <a class="sf-btn" href="{{ route('store.show', $tenant->slug) }}">Browse products</a>
        @else
            <form method="post" action="{{ route('store.cart.update', $tenant->slug) }}">
                @csrf
                @method('PUT')
                <ul class="sf-cart-list">
                    @foreach ($lines as $line)
                        @php $product = $line['product']; @endphp
                        <li class="sf-cart-row">
                            <a class="sf-cart-thumb" href="{{ route('store.product', [$tenant->slug, $product]) }}">
                                @if ($product->imageUrl)
                                    <img src="{{ $product->imageUrl }}" alt="">
                                @else
                                    <span class="sf-card-placeholder" aria-hidden="true">{{ strtoupper(substr($product->name, 0, 1)) }}</span>
                                @endif
                            </a>
                            <div class="sf-cart-meta">
                                <a href="{{ route('store.product', [$tenant->slug, $product]) }}">{{ $product->name }}</a>
                                <span>₦{{ number_format($product->price, 2) }} each</span>
                            </div>
                            <label class="sf-cart-qty">
                                <span>Qty</span>
                                <input type="number" min="0" max="999" name="qty[{{ $product->id }}]" value="{{ $line['qty'] }}">
                            </label>
                            <strong class="sf-cart-line">₦{{ number_format($line['lineTotal'], 2) }}</strong>
                            <button type="submit" form="remove-{{ $product->id }}" class="sf-link-btn">Remove</button>
                        </li>
                    @endforeach
                </ul>
                <div class="sf-cart-actions">
                    <button type="submit" class="sf-btn ghost">Update cart</button>
                    <p class="sf-cart-total">Subtotal <strong>₦{{ number_format($subtotal, 2) }}</strong></p>
                    <a class="sf-btn" href="{{ route('store.checkout', $tenant->slug) }}">Checkout</a>
                </div>
            </form>

            @foreach ($lines as $line)
                <form id="remove-{{ $line['product']->id }}" method="post" action="{{ route('store.cart.remove', $tenant->slug) }}" hidden>
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="productId" value="{{ $line['product']->id }}">
                </form>
            @endforeach
        @endif
    </section>
@endsection
