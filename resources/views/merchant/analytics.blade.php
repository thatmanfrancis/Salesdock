@extends('layouts.app')

@section('title', 'Analytics | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $signed = fn ($amount) => ((float) $amount < 0 ? '−' : '').$money(abs((float) $amount));
    $moved = function ($value) {
        if ($value === null) {
            return null;
        }
        $number = (float) $value;

        return ($number > 0 ? '+' : ($number < 0 ? '−' : '')).number_format(abs($number), 1).'% vs previous';
    };
    $bars = $kpis['gross'] > 0 ? [
        ['label' => 'Gross revenue', 'amount' => $kpis['gross'], 'width' => 100, 'color' => '#22c55e'],
        ['label' => 'Cost of goods', 'amount' => $kpis['cogs'], 'width' => min(100, ($kpis['cogs'] / $kpis['gross']) * 100), 'color' => '#f87171'],
        ['label' => 'VAT', 'amount' => $kpis['vat'], 'width' => min(100, ($kpis['vat'] / $kpis['gross']) * 100), 'color' => '#fbbf24'],
        ['label' => 'Gross profit', 'amount' => max(0, $kpis['profit']), 'width' => max(0, min(100, ($kpis['profit'] / $kpis['gross']) * 100)), 'color' => '#10b981'],
    ] : [];
@endphp

@section('content')
    <div class="page">
        <div class="page-tools">
            <x-ui.filter
                :action="route('analytics')"
                :period="$period"
                :from="$from"
                :to="$to"
                :active="$filtered"
                label="Filter analytics"
                :options="$periods"
            />
        </div>

        <div class="ledger-line">
            <article>
                <span>Gross revenue</span>
                <strong title="{{ $money($kpis['gross']) }}">{{ $money($kpis['gross']) }}</strong>
                @if ($text = $moved($kpis['revenueGrowth']))
                    <em>{{ $text }}</em>
                @endif
            </article>
            <article>
                <span>Cost of goods</span>
                <strong class="cost" title="{{ $money($kpis['cogs']) }}">{{ $money($kpis['cogs']) }}</strong>
                <em>{{ $kpis['cogsShare'] }}% of revenue</em>
            </article>
            <article>
                <span>Gross profit</span>
                <strong class="gain" title="{{ $signed($kpis['profit']) }}">{{ $signed($kpis['profit']) }}</strong>
                @if ($text = $moved($kpis['profitGrowth']))
                    <em>{{ $text }}</em>
                @endif
            </article>
            <article>
                <span>Margin</span>
                <strong class="rate">{{ $kpis['margin'] }}%</strong>
                <em>AOV {{ $money($kpis['aov']) }}</em>
            </article>
        </div>

        <section class="card">
            <h2>Daily revenue · {{ $range }}</h2>
            <div class="day-chart @if ($chart['wide']) wide @endif">
                @foreach ($chart['bars'] as $bar)
                    <div>
                        <i style="height: {{ $bar['height'] }}%" title="{{ $bar['value'] }}"></i>
                        <span>{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($bars !== [])
            <section class="card">
                <h2>Where the revenue goes</h2>
                <div class="pnl">
                    @foreach ($bars as $bar)
                        <div class="pnl-row">
                            <span>{{ $bar['label'] }}</span>
                            <div class="pnl-track"><i style="width: {{ number_format($bar['width'], 1, '.', '') }}%; background: {{ $bar['color'] }}"></i></div>
                            <strong>{{ $money($bar['amount']) }}</strong>
                            <em>{{ number_format($bar['width'], 1) }}%</em>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="split even">
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
            <section class="card">
                <h2>Time of day</h2>
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

        <section class="card">
            <h2>Branch performance</h2>
            @if ($branches === [])
                <p class="empty">No sales in this range.</p>
            @else
                <div class="pnl">
                    @foreach ($branches as $row)
                        <div class="pnl-row">
                            <span>{{ $row['label'] }}</span>
                            <div class="pnl-track"><i style="width: {{ $row['width'] }}%; background: #22c55e"></i></div>
                            <strong>{{ $money($row['amount']) }}</strong>
                            <em>{{ number_format($row['width'], 1) }}%</em>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="split even">
            <section class="card">
                <h2>Top products</h2>
                @if ($products === [])
                    <p class="empty">No sales in this range.</p>
                @else
                    <ol class="rank">
                        @foreach ($products as $index => $product)
                            <li>
                                <span>{{ $index + 1 }}</span>
                                <strong>{{ $product['name'] }}</strong>
                                <em>{{ $money($product['revenue']) }} · {{ number_format($product['qty']) }} units</em>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
            <section class="card orders">
                <p class="kicker band">Cost against margin</p>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="num">Revenue</th>
                            <th class="num">COGS</th>
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
                                <td class="num spend">{{ $money($product['cogs']) }}</td>
                                <td class="num profit">{{ $signed($product['profit']) }}</td>
                                <td class="num"><span class="pill margin {{ $product['tone'] }}">{{ $product['margin'] }}%</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td class="empty" colspan="5">No sales in this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
@endsection
