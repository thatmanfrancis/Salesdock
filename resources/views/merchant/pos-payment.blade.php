@extends('layouts.app')

@section('title', 'Payment confirmation | SalesDock')

@section('content')
    <div class="page">
        <section class="card profile-narrow">
            <h1>{{ $order->status === 'COMPLETED' ? 'Payment confirmed' : 'Payment confirmation' }}</h1>
            <p>Sale {{ $order->paymentRef }} · ₦{{ number_format((float) $order->netAmount, 2) }}</p>
            <p>Order: <strong>{{ $order->status }}</strong> · Payment: <strong>{{ $transaction->status }}</strong></p>
            @if (($transaction->metadata['checkoutState'] ?? null) === 'FAILED')
                <p class="note">The provider confirmed that the last attempt failed. You can open checkout to try again.</p>
            @endif
            @if ($transaction->metadata['needsReview'] ?? false)
                <p class="note ended">{{ $transaction->metadata['reviewReason'] ?? 'This payment needs reconciliation. Ask your manager to review it before collecting another payment.' }}</p>
            @elseif ($order->status === 'PENDING')
                <p class="hint">Stock is set aside. The sale and revenue are recorded only after the payment provider confirms payment.</p>
                <p class="hint">Use online checkout for card or bank transfer. Physical POS terminals are not connected yet.</p>
                <form method="post" action="{{ route('pos.payments.start', $order) }}" target="_blank">
                    @csrf
                    <button type="submit">{{ empty($transaction->metadata['checkoutUrl']) ? 'Open secure checkout' : 'Reopen secure checkout' }}</button>
                </form>
                <form method="post" action="{{ route('pos.payments.refresh', $order) }}">
                    @csrf
                    <button type="submit" class="tool">Check payment status</button>
                </form>
                <p class="hint">If the customer was charged, wait for confirmation before asking them to pay again.</p>
            @endif
            @if ($transaction->gatewayRef)
                <p>Provider reference: {{ $transaction->gatewayRef }}</p>
            @endif
            @if ($order->status === 'COMPLETED')
                <p><a href="{{ route('orders.show', $order) }}" target="_blank" rel="noopener">View order / print receipt</a></p>
            @endif
            <p><a href="{{ route('pos') }}">Back to register</a></p>
        </section>
    </div>
    @if ($order->status === 'PENDING')
        <script>
            (() => {
                let running = false;
                const poll = async () => {
                    if (document.hidden || !navigator.onLine || running) return;
                    running = true;
                    try {
                        const response = await fetch(@json(route('pos.payments.status', $order)), {headers: {Accept: 'application/json'}, cache: 'no-store'});
                        if (!response.ok) return;
                        const state = await response.json();
                        if (state.status !== 'PENDING' || state.needsReview) location.reload();
                    } catch {} finally { running = false; }
                };
                setInterval(poll, 5000);
            })();
        </script>
    @endif
@endsection
