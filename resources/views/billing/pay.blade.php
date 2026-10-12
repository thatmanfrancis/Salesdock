@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Pay invoice | SalesDock')

@section('content')
    <div @class(['page mx-auto w-full max-w-md', 'flex min-h-screen items-center px-4' => ! auth()->check()])>
        <section class="card w-full rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Pay {{ $invoice->invoiceNumber }}</h1>
            <p class="mt-1 text-sm text-gray-500">₦{{ number_format($invoice->amount, 2) }} · {{ $invoice->billingCycle }}</p>
            @if (! auth()->check() && $errors->any())
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif
            @if ($ready)
                <form method="post" action="{{ route('billing.start', ['invoice' => $invoice->invoiceNumber]) }}" data-pay-form>
                    @csrf
                    <button type="submit" class="mt-6 w-full rounded-md bg-green-500 py-3 font-semibold text-white disabled:opacity-60" data-loading="Opening Flutterwave…">Pay with Flutterwave</button>
                </form>
                <p class="mt-3 text-center text-xs text-gray-400">You'll pay on Flutterwave's secure page by card, bank transfer or USSD, then come straight back here.</p>
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
