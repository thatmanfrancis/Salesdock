@extends('layouts.store')

@section('title', 'Pay | '.$config->storeName)

@section('content')
    <section class="sf-panel sf-pay">
        <h1>Pay ₦{{ number_format($order->netAmount, 2) }}</h1>
        <p class="muted">Order {{ $order->paymentRef }} · {{ $config->storeName }}</p>
        @if ((float) $order->serviceCharge > 0)
            <p class="muted">Includes ₦{{ number_format($order->serviceCharge, 2) }} service charge (1.5%).</p>
        @endif
        @if ((float) ($order->deliveryFee ?? 0) > 0)
            <p class="muted">Includes ₦{{ number_format((float) $order->deliveryFee, 2) }} Fez delivery fee.</p>
        @endif
        @if ($errors->any())
            <p class="sf-flash error" role="alert">{{ $errors->first() }}</p>
        @endif
        @if ($ready)
            <form method="post" action="{{ route('store.pay.start', [$tenant->slug, $order->paymentRef]) }}" data-pay-form>
                @csrf
                <button type="submit" class="sf-btn" data-loading="Opening {{ $gatewayLabel }}…">Pay with {{ $gatewayLabel }}</button>
            </form>
            <p class="muted">You'll pay on {{ $gatewayLabel }}'s secure page, then come straight back to your order.</p>
            <script>
                document.querySelector('[data-pay-form]').addEventListener('submit', function (event) {
                    var button = event.target.querySelector('button');
                    button.disabled = true;
                    button.textContent = button.dataset.loading;
                });
            </script>
        @else
            <p>Card checkout is not configured. Your order reference is {{ $order->paymentRef }}.</p>
            <a class="sf-btn ghost" href="{{ route('store.order', [$tenant->slug, $order->paymentRef]) }}">View order</a>
        @endif
    </section>
@endsection
