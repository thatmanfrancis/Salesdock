@extends('layouts.store')

@section('title', $product->name.' | '.$config->storeName)

@section('content')
    @php $inCart = (int) ($cartItems[$product->id] ?? 0); @endphp

    <nav class="sf-crumb">
        <a href="{{ route('store.show', $tenant->slug) }}">All products</a>
        <span>/</span>
        <span>{{ $product->name }}</span>
    </nav>

    <section class="sf-product">
        <div class="sf-product-media">
            @if ($product->imageUrl)
                <img src="{{ $product->imageUrl }}" alt="{{ $product->name }}">
            @else
                <span class="sf-card-placeholder large" aria-hidden="true">{{ strtoupper(substr($product->name, 0, 1)) }}</span>
            @endif
        </div>
        <div class="sf-product-info">
            <h1>{{ $product->name }}</h1>
            @if ($product->category)
                <p class="sf-tag">{{ $product->category }}</p>
            @endif
            <p class="sf-price">₦{{ number_format($product->price, 2) }}</p>
            @if ($product->isDiscounted && $product->originalPrice)
                <p class="sf-was">₦{{ number_format($product->originalPrice, 2) }}</p>
            @endif
            @if ($product->description)
                <p class="sf-desc">{{ $product->description }}</p>
            @endif
            <p class="sf-stock {{ $available < 1 ? 'out' : '' }}">
                {{ $available < 1 ? 'Out of stock' : $available.' in stock' }}
            </p>

            @if ($available > 0)
                <form method="post" action="{{ route('store.cart.add', $tenant->slug) }}" class="sf-buy" data-sf-cart-add>
                    @csrf
                    <input type="hidden" name="productId" value="{{ $product->id }}">
                    <label>
                        <span>Qty</span>
                        <input type="number" name="qty" min="1" max="{{ $available }}" value="1">
                    </label>
                    <button type="submit" class="{{ $inCart > 0 ? 'in-cart' : '' }}" data-qty="{{ $inCart }}">
                        {{ $inCart > 0 ? 'In cart · '.$inCart : 'Add to cart' }}
                    </button>
                </form>
            @endif
        </div>
    </section>
@endsection
