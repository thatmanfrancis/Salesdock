@extends('layouts.store')

@section('title', 'Order | '.$config->storeName)

@section('content')
    <section class="sf-panel">
        <header class="sf-panel-head">
            <h1>Order {{ $order->paymentRef }}</h1>
            <a href="{{ route('store.show', $tenant->slug) }}">Back to store</a>
        </header>

        <p class="sf-order-status">
            <span class="sf-pill {{ strtolower($order->status) }}">{{ $order->status }}</span>
            <strong>₦{{ number_format($order->netAmount, 2) }}</strong>
        </p>
        <p class="muted">{{ $order->customerName }} · {{ $order->customerPhone }}</p>
        @if ($order->customerAddress)
            <p class="muted">{{ $order->customerAddress }}</p>
        @endif

        @if ($order->status === 'PENDING' && $config->bankName)
            <div class="sf-hint">
                <p>Pay by transfer to complete this order:</p>
                <p><strong>{{ $config->accountName }}</strong></p>
                <p>{{ $config->bankName }} · {{ $config->bankAccount }}</p>
                <p>Use reference <strong>{{ $order->paymentRef }}</strong></p>
            </div>
        @endif

        <table class="sf-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $products[$item->productId]->name ?? 'Product' }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>₦{{ number_format($item->lineTotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection
