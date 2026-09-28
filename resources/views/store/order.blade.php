@extends('layouts.store')

@section('title', 'Order | '.$config->storeName)

@section('content')
    <section>
        <h2>Order {{ $order->paymentRef }}</h2>
        <p>{{ $order->status }} · ₦{{ number_format($order->netAmount, 2) }}</p>
        <p>{{ $order->customerName }} · {{ $order->customerPhone }}</p>
        <table>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item->quantity }}</td>
                    <td>₦{{ number_format($item->lineTotal, 2) }}</td>
                </tr>
            @endforeach
        </table>
    </section>
@endsection
