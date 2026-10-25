@extends('layouts.store')

@section('title', 'Checkout | '.$config->storeName)

@php
    $serviceFee    = round($subtotal * $serviceRate, 2);
    $cardTotal     = $subtotal + $serviceFee;
    $method        = old('method', 'CARD');
    $displayTotal  = $method === 'CARD' ? $cardTotal : $subtotal;
    $deliveryMethod = old('deliveryMethod', 'PICKUP');
@endphp

@section('content')
    <div class="sf-checkout">
        <section class="sf-panel">
            <header class="sf-panel-head">
                <h1>Checkout</h1>
                <a href="{{ route('store.cart', $tenant->slug) }}">Back to cart</a>
            </header>

            @if ($errors->any())
                <p class="sf-flash error" role="alert">{{ $errors->first() }}</p>
            @endif
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
                @if ($deliveryAccount)
                    <label>
                        <span>Delivery method</span>
                        <select name="deliveryMethod" id="delivery-method">
                            <option value="PICKUP" @selected($deliveryMethod === 'PICKUP')>Pickup from shop</option>
                            <option value="FEZ" @selected($deliveryMethod === 'FEZ')>Fez Delivery</option>
                        </select>
                    </label>
                    <div id="fez-fields" @if($deliveryMethod !== 'FEZ') style="display:none" @endif>
                        <label>
                            <span>Delivery state</span>
                            <select name="deliveryState" id="delivery-state">
                                <option value="">Choose state…</option>
                                @foreach ($deliveryStates as $state)
                                    <option value="{{ $state }}" @selected(old('deliveryState') === $state)>{{ $state }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Alternate phone (optional)</span>
                            <input name="recipientAlternatePhone" value="{{ old('recipientAlternatePhone') }}" autocomplete="tel">
                        </label>
                        <label>
                            <span>Landmark / delivery note (optional)</span>
                            <textarea name="deliveryNotes" rows="2" placeholder="Nearest landmark, gate colour, estate, etc.">{{ old('deliveryNotes') }}</textarea>
                        </label>
                        <p class="sf-hint" id="delivery-quote-note">Choose a state to get the Fez delivery price. The final price is confirmed again when you place the order.</p>
                    </div>
                @else
                    <input type="hidden" name="deliveryMethod" value="PICKUP">
                    <p class="sf-hint">Pickup from the shop. This shop has not enabled Fez delivery.</p>
                @endif
                <label>
                    <span>{{ $deliveryAccount ? 'Delivery / pickup address' : 'Delivery address' }}</span>
                    <textarea name="customerAddress" rows="3" placeholder="Street, area, city">{{ old('customerAddress') }}</textarea>
                </label>
                <label>
                    <span>Payment method</span>
                    <select name="method" id="checkout-method">
                        <option value="CARD" @selected($method === 'CARD')>Card / {{ $gatewayLabel ?? 'Online' }}</option>
                        <option value="TRANSFER" @selected($method === 'TRANSFER')>Bank transfer</option>
                    </select>
                </label>
                @if ($config->bankName)
                    <p class="sf-hint" id="transfer-hint" @if($method !== 'TRANSFER') style="display:none" @endif>
                        Transfer to {{ $config->accountName }} · {{ $config->bankName }} · {{ $config->bankAccount }}
                    </p>
                @endif
                <x-turnstile action="checkout" />
                <button type="submit" class="sf-btn" id="place-btn" data-captcha-submit @disabled(\App\Support\Turnstile::needsChallenge())>
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
                    <span>Service charge ({{ rtrim(rtrim(number_format($serviceRate * 100, 2), '0'), '.') }}%)</span>
                    <span id="fee-amount">₦{{ number_format($serviceFee, 2) }}</span>
                </p>
                <p id="delivery-fee-row" @if($deliveryMethod !== 'FEZ') style="display:none" @endif>
                    <span>Fez delivery</span>
                    <span id="delivery-fee-amount">₦0.00</span>
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
            var deliveryMethod = document.getElementById('delivery-method');
            var deliveryState = document.getElementById('delivery-state');
            var fezFields = document.getElementById('fez-fields');
            var deliveryRow = document.getElementById('delivery-fee-row');
            var deliveryAmt = document.getElementById('delivery-fee-amount');
            var deliveryNote = document.getElementById('delivery-quote-note');
            var deliveryFee = 0;
            var quoteSeq = 0;
            @if($config->bankName)
            var transferHint = document.getElementById('transfer-hint');
            @endif

            function fmt(n) {
                return '₦' + n.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function update() {
                var isCard = method.value === 'CARD';
                var fee    = isCard ? Math.round(subtotal * serviceRate * 100) / 100 : 0;
                var total  = subtotal + fee + deliveryFee;

                if (feeRow)   feeRow.style.display   = isCard ? '' : 'none';
                if (feeAmt)   feeAmt.textContent      = fmt(fee);
                if (deliveryRow) deliveryRow.style.display = deliveryMethod && deliveryMethod.value === 'FEZ' ? '' : 'none';
                if (deliveryAmt) deliveryAmt.textContent = fmt(deliveryFee);
                if (summaryTotal) summaryTotal.textContent = fmt(total);
                if (btnAmount)    btnAmount.textContent    = fmt(total);
                @if($config->bankName)
                if (transferHint) transferHint.style.display = isCard ? 'none' : '';
                @endif
            }

            function refreshQuote() {
                if (!deliveryMethod || deliveryMethod.value !== 'FEZ') {
                    deliveryFee = 0;
                    if (fezFields) fezFields.style.display = 'none';
                    update();
                    return;
                }
                if (fezFields) fezFields.style.display = '';
                if (!deliveryState || !deliveryState.value) {
                    deliveryFee = 0;
                    if (deliveryNote) deliveryNote.textContent = 'Choose a state to get the Fez delivery price.';
                    update();
                    return;
                }
                var seq = ++quoteSeq;
                if (deliveryNote) deliveryNote.textContent = 'Getting Fez delivery price…';
                fetch('{{ route('store.delivery-quote', $tenant->slug) }}?state=' + encodeURIComponent(deliveryState.value), { headers: { 'Accept': 'application/json' } })
                    .then(function (response) { return response.json().then(function (body) { return { ok: response.ok, body: body }; }); })
                    .then(function (result) {
                        if (seq !== quoteSeq) return;
                        if (!result.ok || !result.body.ok) throw new Error(result.body.message || 'Quote failed');
                        deliveryFee = Number(result.body.fee || 0);
                        if (deliveryNote) deliveryNote.textContent = 'Fez delivery price confirmed for this cart. It will be checked again at order placement.';
                        update();
                    })
                    .catch(function (error) {
                        if (seq !== quoteSeq) return;
                        deliveryFee = 0;
                        if (deliveryNote) deliveryNote.textContent = error.message || 'Could not get a Fez delivery price.';
                        update();
                    });
            }

            method.addEventListener('change', update);
            if (deliveryMethod) deliveryMethod.addEventListener('change', refreshQuote);
            if (deliveryState) deliveryState.addEventListener('change', refreshQuote);
            update();
            refreshQuote();
        })();
    </script>
@endsection
