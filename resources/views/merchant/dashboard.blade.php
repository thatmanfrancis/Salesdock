@extends('layouts.app')

@section('title', 'Dashboard | SalesDock')

@section('content')
    <div class="page">
        <div class="page-tools">
            @if ($pos)
                <a class="btn" href="{{ route('pos') }}">Open POS</a>
            @endif
            <x-ui.filter
                :action="route('dashboard')"
                :period="$period"
                :from="$from"
                :to="$to"
                :options="[
                    'today' => 'Today',
                    'yesterday' => 'Yesterday',
                    '7' => 'Last 7 days',
                    '30' => 'Last 30 days',
                    'custom' => 'Custom range',
                ]"
            />
        </div>

        @if ($stats !== [])
            <div class="stats">
                @foreach ($stats as $stat)
                    <article>
                        <span>{{ $stat['label'] }}</span>
                        <strong>{{ $stat['value'] }}</strong>
                    </article>
                @endforeach
            </div>
        @endif

        <section class="card">
            <h2>Sales · {{ $periodLabel }}</h2>
            <div class="day-chart">
                @foreach ($chart as $bar)
                    <div>
                        <i style="height: {{ $bar['height'] > 0 ? max($bar['height'], 8) : 0 }}%" title="{{ $bar['value'] }}"></i>
                        <span>{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="split">
            <section class="card">
                <div class="page-head">
                    <h2>Recent orders</h2>
                    @if ($ordersLink)
                        <a href="{{ route('orders') }}">View all</a>
                    @endif
                </div>
                @if ($orders->isEmpty())
                    <p class="empty">No orders yet.</p>
                @else
                    <table>
                        <thead>
                            <tr><th>When</th><th>Status</th><th>Customer</th><th>Net</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td>{{ $order['when'] }}</td>
                                    <td>{{ $order['status'] }}</td>
                                    <td>
                                        @if ($order['href'])
                                            <a href="{{ $order['href'] }}">{{ $order['customer'] }}</a>
                                        @else
                                            {{ $order['customer'] }}
                                        @endif
                                    </td>
                                    <td>{{ $order['net'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            @if ($top !== null)
                <section class="card">
                    <h2>Top products · {{ $periodLabel }}</h2>
                    @if ($top->isEmpty())
                        <p class="empty">No products sold in this period.</p>
                    @else
                        <table>
                            <thead>
                                <tr><th>Product</th><th>Qty</th><th>Sales</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($top as $item)
                                    <tr>
                                        <td>{{ $item['name'] }}</td>
                                        <td>{{ $item['qty'] }}</td>
                                        <td>{{ $item['total'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            @endif
        </div>
    </div>
@endsection
