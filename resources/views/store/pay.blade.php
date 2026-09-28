@extends('layouts.store')

@section('title', 'Pay | '.$config->storeName)

@section('content')
    <section>
        <h2>Pay ₦{{ number_format($order->netAmount, 2) }}</h2>
        @if ($publicKey)
            <button type="button" id="pay">Pay with Flutterwave</button>
        @else
            <p>Card checkout is not configured. Your order reference is {{ $order->paymentRef }}.</p>
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
