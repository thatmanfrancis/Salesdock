@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Pay invoice | SalesDock')

@section('content')
    <div @class(['page mx-auto w-full max-w-md', 'flex min-h-screen items-center px-4' => ! auth()->check()])>
        <section class="card w-full rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Pay {{ $invoice->invoiceNumber }}</h1>
            <p class="mt-1 text-sm text-gray-500">₦{{ number_format($invoice->amount, 2) }} · {{ $invoice->billingCycle }}</p>
            @if ($publicKey)
                <button type="button" id="pay" class="mt-6 w-full rounded-xl bg-green-500 py-3 font-semibold text-white">Pay with Flutterwave</button>
                <p class="mt-3 text-center text-xs text-gray-400">You'll be redirected to your billing page after payment.</p>
                <script src="https://checkout.flutterwave.com/v3.js"></script>
                <script>
                    document.getElementById('pay').addEventListener('click', function () {
                        FlutterwaveCheckout({
                            public_key: @json($publicKey),
                            tx_ref: @json($invoice->invoiceNumber),
                            amount: {{ (float) $invoice->amount }},
                            currency: 'NGN',
                            payment_options: 'card,banktransfer,ussd',
                            customer: { email: @json($email), name: @json($name) },
                            customizations: { title: 'SalesDock', description: 'Subscription' },
                            redirect_url: @json($redirect),
                            callback: function (data) {
                                // Payment completed via popup — navigate to verify route
                                window.location.href = @json($redirect) + '&transaction_id=' + data.transaction_id;
                            },
                            onclose: function () {
                                // User closed the popup without paying — send them to billing
                                window.location.href = @json(auth()->check() ? route('billing') : route('login'));
                            },
                        });
                    });
                </script>
            @else
                <p>Add FLUTTERWAVE_PUBLIC_KEY and FLUTTERWAVE_SECRET_KEY to the environment. The invoice stays pending until then.</p>
            @endif
        </section>
    </div>
@endsection
