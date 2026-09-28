@extends('layouts.app')

@section('title', 'Analytics | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $signed = fn ($amount) => ((float) $amount < 0 ? '−' : '').$money(abs((float) $amount));
@endphp

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Analytics</h1>
                <span class="tally">{{ $periodLabel }}</span>
            </div>
            <x-ui.filter
                :action="route('admin.analytics')"
                :period="$period"
                :from="$from"
                :to="$to"
                :active="$filtered"
                label="Filter analytics"
                :options="[
                    'today' => 'Today',
                    'yesterday' => 'Yesterday',
                    '7' => 'Last 7 days',
                    '30' => 'Last 30 days',
                    '90' => 'Last 90 days',
                    '365' => 'Last year',
                    'custom' => 'Custom range',
                ]"
            />
        </div>

        <div class="ledger-line">
            <article>
                <span>Gross revenue</span>
                <strong title="{{ $money($kpis['gross']) }}">{{ $money($kpis['gross']) }}</strong>
                @if ($kpis['revenueGrowth'])
                    <em>{{ $kpis['revenueGrowth'] }}</em>
                @endif
            </article>
            <article>
                <span>COGS</span>
                <strong class="cost" title="{{ $money($kpis['cogs']) }}">{{ $money($kpis['cogs']) }}</strong>
                <em>{{ $kpis['cogsShare'] }}% of revenue</em>
            </article>
            <article>
                <span>Gross profit</span>
                <strong class="gain" title="{{ $signed($kpis['profit']) }}">{{ $signed($kpis['profit']) }}</strong>
                @if ($kpis['profitGrowth'])
                    <em>{{ $kpis['profitGrowth'] }}</em>
                @endif
            </article>
            <article>
                <span>Margin</span>
                <strong class="rate">{{ $kpis['margin'] }}%</strong>
                <em>AOV {{ $money($kpis['aov']) }}</em>
            </article>
            <article>
                <span>Transactions</span>
                <strong>{{ number_format($kpis['orders']) }}</strong>
            </article>
            <article>
                <span>Average order</span>
                <strong class="rate" title="{{ $money($kpis['aov']) }}">{{ $money($kpis['aov']) }}</strong>
            </article>
            <article>
                <span>VAT</span>
                <strong class="vat" title="{{ $money($kpis['vat']) }}">{{ $money($kpis['vat']) }}</strong>
            </article>
            <article>
                <span>Net revenue</span>
                <strong class="gain" title="{{ $money($kpis['net']) }}">{{ $money($kpis['net']) }}</strong>
            </article>
        </div>

        <section class="card">
            <h2>{{ $hourly ? 'Sales' : 'Daily revenue' }} · {{ $periodLabel }}</h2>
            <div @class(['day-chart', 'platform', 'wide' => $wide])>
                @foreach ($chart as $bar)
                    <div>
                        <i style="height: {{ $bar['height'] > 0 ? max($bar['height'], 8) : 0 }}%" title="{{ $bar['title'] }}"></i>
                        <span>{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="split even">
            <section class="card">
                <h2>Top shops</h2>
                @if ($shops === [])
                    <p class="empty">No sales.</p>
                @else
                    <ol class="rank">
                        @foreach ($shops as $index => $shop)
                            <li>
                                <span>{{ $index + 1 }}</span>
                                <strong><a href="{{ route('admin.tenants.show', $shop['id']) }}">{{ $shop['name'] }}</a></strong>
                                <em>{{ $money($shop['revenue']) }} · {{ number_format($shop['orders']) }} orders</em>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
            <section class="card">
                <h2>Revenue by channel</h2>
                <div class="pnl">
                    @foreach ($channels as $row)
                        <div class="pnl-row">
                            <span>{{ $row['label'] }}</span>
                            <div class="pnl-track"><i style="width: {{ $row['width'] }}%; background: #22c55e"></i></div>
                            <strong>{{ $money($row['amount']) }}</strong>
                            <em>{{ number_format($row['width'], 1) }}%</em>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="card orders">
            <p class="kicker band">Top products</p>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="num">Revenue</th>
                        <th class="num">Profit</th>
                        <th class="num">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>
                                {{ $product['name'] }}
                                @if ($product['sku'])
                                    <span class="sku">{{ $product['sku'] }}</span>
                                @endif
                            </td>
                            <td class="num">{{ $money($product['revenue']) }}</td>
                            <td class="num profit">{{ $signed($product['profit']) }}</td>
                            <td class="num"><span class="pill margin {{ $product['tone'] }}">{{ $product['margin'] }}%</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="4">No sales.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2>Sales by time of day</h2>
            <div class="pnl">
                @foreach ($dayparts as $row)
                    <div class="pnl-row">
                        <span>{{ $row['label'] }}</span>
                        <div class="pnl-track"><i style="width: {{ $row['width'] }}%; background: #22c55e"></i></div>
                        <strong>{{ $money($row['amount']) }}</strong>
                        <em>{{ number_format($row['width'], 1) }}%</em>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
