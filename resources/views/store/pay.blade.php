@extends('layouts.store')

@section('title', 'Pay | '.$config->storeName)

@section('content')
    <section class="sf-panel sf-pay">
        <h1>Pay ₦{{ number_format($order->netAmount, 2) }}</h1>
        <p class="muted">Order {{ $order->paymentRef }} · {{ $config->storeName }}</p>
        @if ($publicKey)
            <button type="button" id="pay" class="sf-btn">Pay with Flutterwave</button>
        @else
            <p>Card checkout is not configured. Your order reference is {{ $order->paymentRef }}.</p>
            <a class="sf-btn ghost" href="{{ route('store.order', [$tenant->slug, $order->paymentRef]) }}">View order</a>
        @endif
    </section>
    @if ($publicKey)
        <script src="https://checkout.flutterwave.com/v3.js"></script>
        <script>
            document.getElementById('pay').addEventListener('click', function () {
                FlutterwaveCheckout({
                    public_key: @json($publicKey),
                    tx_ref: @json($order->paymentRef),
                    amount: {{ (float) $order->netAmount }},
                    currency: 'NGN',
                    payment_options: 'card,banktransfer,ussd',
                    customer: { email: @json($order->customerEmail), name: @json($order->customerName) },
                    customizations: { title: @json($config->storeName), description: 'Order' },
                    redirect_url: @json($redirect),
                });
            });
        </script>
    @endif
@endsection
