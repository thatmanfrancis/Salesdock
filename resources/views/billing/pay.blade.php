@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Pay invoice | SalesDock')

@section('content')
    <div @class(['page mx-auto w-full max-w-md', 'flex min-h-screen items-center px-4' => ! auth()->check()])>
        <section class="card w-full rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Pay {{ $invoice->invoiceNumber }}</h1>
            <p class="mt-1 text-sm text-gray-500">₦{{ number_format($invoice->amount, 2) }} · {{ $invoice->billingCycle }}</p>
            @if ($publicKey)
                <div id="pay-error" class="mt-4 hidden rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"></div>
                <button type="button" id="pay" class="mt-6 w-full rounded-xl bg-green-500 py-3 font-semibold text-white">Pay with Flutterwave</button>
                <p class="mt-3 text-center text-xs text-gray-400">You'll be redirected to your billing page after payment.</p>
                <script src="https://checkout.flutterwave.com/v3.js"></script>
                <script>
                    document.getElementById('pay').addEventListener('click', function () {
                        var errBox = document.getElementById('pay-error');
                        errBox.classList.add('hidden');
                        errBox.textContent = '';
                        try {
                            FlutterwaveCheckout({
                                public_key: @json($publicKey),
                                tx_ref: @json($invoice->invoiceNumber),
                                amount: {{ (float) $invoice->amount }},
                                currency: 'NGN',
                                payment_options: 'card,banktransfer,ussd',
                                customer: { email: @json($email), name: @json($name) },
                                customizations: { title: 'SalesDock', description: 'Subscription — ' + @json($invoice->invoiceNumber) },
                                redirect_url: @json($redirect),
                                callback: function (data) {
                                    window.location.href = @json($redirect) + '&transaction_id=' + data.transaction_id;
                                },
                                onclose: function () {
                                    window.location.href = @json(auth()->check() ? route('billing') : route('login'));
                                },
                            });
                        } catch (e) {
                            errBox.textContent = 'Payment could not be initialised. Please contact support if this persists. (' + e.message + ')';
                            errBox.classList.remove('hidden');
                        }
                    });
                </script>
            @else
                <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    Payment is not configured yet. Please contact support.
                </div>
                <a href="{{ auth()->check() ? route('billing') : route('login') }}" class="mt-4 block text-center text-sm text-gray-500 hover:text-gray-700">← Go back</a>
            @endif
        </section>
    </div>
@endsection
