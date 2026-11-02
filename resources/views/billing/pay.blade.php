@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Pay invoice | SalesDock')

@section('content')
    <div @class(['page mx-auto w-full max-w-md', 'flex min-h-screen items-center px-4' => ! auth()->check()])>
        <section class="card w-full rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Pay {{ $invoice->invoiceNumber }}</h1>
            <p class="mt-1 text-sm text-gray-500">₦{{ number_format($invoice->amount, 2) }} · {{ $invoice->billingCycle }}</p>
            <p class="mt-3 text-sm text-gray-600">Payment schedule: {{ \App\Services\SubscriptionPricing::description(\App\Services\SubscriptionPricing::forInvoice($invoice)) }}</p>
            <p class="mt-3 text-sm text-gray-600">A plan change takes effect after successful payment and starts a new billing period. The full selected price is payable now; unused time is not automatically prorated or credited. Opening checkout for a different plan or interval pauses the previous automatic renewal. Future automatic renewals require approval below.</p>
            @if (! auth()->check() && $errors->any())
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif
            @if ($ready)
                <form method="post" action="{{ route('billing.start', ['invoice' => $invoice->invoiceNumber]) }}" data-pay-form>
                    @csrf
                    @if (($invoice->billingMetadata['checkoutState'] ?? '') === 'READY')
                        <p class="mt-4 text-sm text-gray-600">Resume the checkout already opened for this invoice. {{ ($invoice->billingMetadata['autoRenewRequested'] ?? false) ? 'Automatic renewal was requested with the authorisation recorded for this checkout. You can withdraw it in Billing before or after payment.' : 'This checkout is a one-time card payment.' }}</p>
                    @elseif ($canAutoRenew ?? false)
                        <input type="hidden" name="authorizationVersion" value="{{ \App\Services\RecurringBilling::TERMS_VERSION }}">
                        <label class="mt-5 flex items-start gap-3 text-sm text-gray-700">
                            <input type="checkbox" name="autoRenew" value="1" class="mt-1 shrink-0">
                            <span>Enable automatic renewal: {{ \App\Services\RecurringBilling::consentText((float) $invoice->amount, $invoice->billingCycle, \App\Services\SubscriptionPricing::forInvoice($invoice)) }}</span>
                        </label>
                        <p class="mt-3 text-sm text-gray-600">First renewal: {{ $invoice->periodEnd?->timezone('Africa/Lagos')->format('d M Y') }}. Leave the box unchecked for a one-time card payment. <a class="underline" href="{{ route('recurring.terms') }}" target="_blank" rel="noopener">Read the authorisation</a>.</p>
                    @else
                        <p class="mt-4 text-sm text-gray-600">This is a one-time card payment. Automatic renewal is available only to the signed-in shop owner when enabled for the payment provider.</p>
                    @endif
                    <button type="submit" class="mt-6 w-full rounded-md bg-green-500 py-3 font-semibold text-white disabled:opacity-60" data-loading="Opening {{ $gatewayLabel }}…">Pay with {{ $gatewayLabel }}</button>
                </form>
                <p class="mt-3 text-center text-xs text-gray-400">Software subscriptions are paid by card on {{ $gatewayLabel }}'s secure page. SalesDock does not collect or store your full card number or CVV.</p>
                <p class="mt-3 text-center text-sm"><a class="underline" href="{{ route('terms') }}">Terms</a> · <a class="underline" href="{{ route('privacy') }}">Privacy</a> · <a class="underline" href="{{ route('recurring.terms') }}">Recurring card authorisation</a></p>
                <script>
                    document.querySelector('[data-pay-form]').addEventListener('submit', function (event) {
                        var button = event.target.querySelector('button');
                        button.disabled = true;
                        button.textContent = button.dataset.loading;
                    });
                </script>
            @else
                <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    Payment is not configured yet. Please contact support.
                </div>
            @endif
            <a href="{{ auth()->check() ? route('billing') : route('login') }}" class="mt-4 block text-center text-sm text-gray-500 hover:text-gray-700">← Go back</a>
        </section>
    </div>
@endsection
