@extends('layouts.store')

@section('title', 'Checkout | '.$config->storeName)

@php
    $serviceRate   = 0.015; // 1.5% Flutterwave charge on card payments
    $serviceFee    = round($subtotal * $serviceRate, 2);
    $cardTotal     = $subtotal + $serviceFee;
    $method        = old('method', 'CARD');
    $displayTotal  = $method === 'CARD' ? $cardTotal : $subtotal;
@endphp

@section('content')
    <div class="sf-checkout">
        <section class="sf-panel">
            <header class="sf-panel-head">
                <h1>Checkout</h1>
                <a href="{{ route('store.cart', $tenant->slug) }}">Back to cart</a>
            </header>

            <form method="post" action="{{ route('store.checkout.store', $tenant->slug) }}" class="sf-form" id="checkout-form">
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
                    <select name="method" id="checkout-method">
                        <option value="CARD" @selected($method === 'CARD')>Card / Flutterwave</option>
                        <option value="TRANSFER" @selected($method === 'TRANSFER')>Bank transfer</option>
                    </select>
                </label>
                @if ($config->bankName)
                    <p class="sf-hint" id="transfer-hint" @if($method !== 'TRANSFER') style="display:none" @endif>
                        Transfer to {{ $config->accountName }} · {{ $config->bankName }} · {{ $config->bankAccount }}
                    </p>
                @endif
                <button type="submit" class="sf-btn" id="place-btn">
                    Place order · <span id="btn-amount">₦{{ number_format($displayTotal, 2) }}</span>
                </button>
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
            <div class="sf-summary-fees">
                <p><span>Subtotal</span><span>₦{{ number_format($subtotal, 2) }}</span></p>
                <p id="fee-row" @if($method !== 'CARD') style="display:none" @endif>
                    <span>Service charge (1.5%)</span>
                    <span id="fee-amount">₦{{ number_format($serviceFee, 2) }}</span>
                </p>
            </div>
            <p class="sf-cart-total" id="summary-total">
                Total <strong>₦{{ number_format($displayTotal, 2) }}</strong>
            </p>
        </aside>
    </div>

    <script>
        (function () {
            var subtotal    = {{ $subtotal }};
            var serviceRate = {{ $serviceRate }};
            var method      = document.getElementById('checkout-method');
            var feeRow      = document.getElementById('fee-row');
            var feeAmt      = document.getElementById('fee-amount');
            var summaryTotal = document.getElementById('summary-total').querySelector('strong');
            var btnAmount   = document.getElementById('btn-amount');
            @if($config->bankName)
            var transferHint = document.getElementById('transfer-hint');
            @endif

            function fmt(n) {
                return '₦' + n.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function update() {
                var isCard = method.value === 'CARD';
                var fee    = isCard ? Math.round(subtotal * serviceRate * 100) / 100 : 0;
                var total  = subtotal + fee;

                if (feeRow)   feeRow.style.display   = isCard ? '' : 'none';
                if (feeAmt)   feeAmt.textContent      = fmt(fee);
                if (summaryTotal) summaryTotal.textContent = fmt(total);
                if (btnAmount)    btnAmount.textContent    = fmt(total);
                @if($config->bankName)
                if (transferHint) transferHint.style.display = isCard ? 'none' : '';
                @endif
            }

            method.addEventListener('change', update);
            update();
        })();
    </script>
@endsection
