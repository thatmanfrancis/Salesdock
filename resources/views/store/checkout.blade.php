@extends('layouts.store')

@section('title', 'Checkout | '.$config->storeName)

@section('content')
    <div class="sf-checkout">
        <section class="sf-panel">
            <header class="sf-panel-head">
                <h1>Checkout</h1>
                <a href="{{ route('store.cart', $tenant->slug) }}">Back to cart</a>
            </header>

            <form method="post" action="{{ route('store.checkout.store', $tenant->slug) }}" class="sf-form">
                @csrf
                <label>
                    <span>Full name</span>
                    <input name="customerName" value="{{ old('customerName') }}" required autocomplete="name">
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="customerEmail" value="{{ old('customerEmail') }}" required autocomplete="email">
                </label>
                <label>
                    <span>Phone</span>
                    <input name="customerPhone" value="{{ old('customerPhone') }}" required autocomplete="tel">
                </label>
                <label>
                    <span>Delivery address</span>
                    <textarea name="customerAddress" rows="3" placeholder="Street, area, city">{{ old('customerAddress') }}</textarea>
                </label>
                <label>
                    <span>Payment method</span>
                    <select name="method">
                        <option value="CARD" @selected(old('method', 'CARD') === 'CARD')>Card / Flutterwave</option>
                        <option value="TRANSFER" @selected(old('method') === 'TRANSFER')>Bank transfer</option>
                    </select>
                </label>
                @if ($config->bankName && old('method') === 'TRANSFER')
                    <p class="sf-hint">Transfer to {{ $config->accountName }} · {{ $config->bankName }} · {{ $config->bankAccount }}</p>
                @endif
                <button type="submit" class="sf-btn">Place order · ₦{{ number_format($subtotal, 2) }}</button>
            </form>
        </section>

        <aside class="sf-panel sf-summary">
            <h2>Order summary</h2>
            <ul class="sf-summary-list">
                @foreach ($lines as $line)
                    <li>
                        <span>{{ $line['qty'] }} × {{ $line['product']->name }}</span>
                        <strong>₦{{ number_format($line['lineTotal'], 2) }}</strong>
                    </li>
                @endforeach
            </ul>
            <p class="sf-cart-total">Total <strong>₦{{ number_format($subtotal, 2) }}</strong></p>
        </aside>
    </div>
@endsection
