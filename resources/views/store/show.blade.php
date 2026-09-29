@extends('layouts.store')

@section('title', ($config->metaTitle ?: $config->storeName).' | SalesDock')

@section('content')
    @if ($config->bannerImageUrl)
        <section class="sf-banner" aria-label="Store banner">
            <img src="{{ $config->bannerImageUrl }}" alt="{{ $config->storeName }}">
        </section>
    @endif

    @if ($categories->isNotEmpty())
        <nav class="sf-cats" aria-label="Categories">
            <a href="{{ route('store.show', $tenant->slug) }}" class="{{ $category === '' ? 'on' : '' }}">All</a>
            @foreach ($categories as $cat)
                <a
                    href="{{ route('store.show', ['slug' => $tenant->slug, 'category' => $cat, 'q' => $search ?: null]) }}"
                    class="{{ $category === $cat ? 'on' : '' }}"
                >{{ $cat }}</a>
            @endforeach
        </nav>
    @endif

    @if ($search !== '')
        <p class="sf-results">Results for “{{ $search }}”</p>
    @endif

    <section class="sf-grid" aria-label="Products">
        @forelse ($products as $product)
            @php
                $available = max(0, (int) $product->currentStock - (int) $product->reservedQty);
                $inCart = (int) ($cartItems[$product->id] ?? 0);
            @endphp
            <article class="sf-card">
                <a class="sf-card-media" href="{{ route('store.product', [$tenant->slug, $product]) }}">
                    @if ($product->imageUrl)
                        <img src="{{ $product->imageUrl }}" alt="{{ $product->name }}" loading="lazy">
                    @else
                        <span class="sf-card-placeholder" aria-hidden="true">{{ strtoupper(substr($product->name, 0, 1)) }}</span>
                    @endif
                </a>
                <div class="sf-card-body">
                    <a href="{{ route('store.product', [$tenant->slug, $product]) }}">{{ $product->name }}</a>
                    <strong>₦{{ number_format($product->price, 2) }}</strong>
                    @if ($product->isDiscounted && $product->originalPrice)
                        <span class="sf-was">₦{{ number_format($product->originalPrice, 2) }}</span>
                    @endif
                    @if ($available < 1)
                        <span class="sf-stock out">Out of stock</span>
                    @elseif ($available <= 5)
                        <span class="sf-stock">Only {{ $available }} left</span>
                    @endif
                </div>
                @if ($available > 0)
                    <form method="post" action="{{ route('store.cart.add', $tenant->slug) }}" class="sf-card-action" data-sf-cart-add>
                        @csrf
                        <input type="hidden" name="productId" value="{{ $product->id }}">
                        <button type="submit" class="{{ $inCart > 0 ? 'in-cart' : '' }}" data-qty="{{ $inCart }}">
                            {{ $inCart > 0 ? 'In cart · '.$inCart : 'Add to cart' }}
                        </button>
                    </form>
                @else
                    <p class="sf-card-action muted">Unavailable</p>
                @endif
            </article>
        @empty
            <p class="sf-empty">No products match this view yet.</p>
        @endforelse
    </section>
@endsection
