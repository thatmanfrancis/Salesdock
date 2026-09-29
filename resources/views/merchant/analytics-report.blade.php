@extends('merchant.report')

@section('body')
    @php
        $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
        $signed = fn ($amount) => ((float) $amount < 0 ? '−' : '').$money(abs((float) $amount));
    @endphp

    <div class="totals">
        <div><span>Gross revenue</span><strong>{{ $money($kpis['gross']) }}</strong></div>
        <div><span>Cost of goods</span><strong>{{ $money($kpis['cogs']) }}</strong></div>
        <div><span>Gross profit</span><strong>{{ $signed($kpis['profit']) }}</strong></div>
        <div><span>Margin</span><strong>{{ $kpis['margin'] }}%</strong></div>
        <div><span>Orders</span><strong>{{ number_format($kpis['orders']) }}</strong></div>
        <div><span>AOV</span><strong>{{ $money($kpis['aov']) }}</strong></div>
        <div><span>VAT</span><strong>{{ $money($kpis['vat']) }}</strong></div>
        <div><span>Net revenue</span><strong>{{ $money($kpis['net']) }}</strong></div>
    </div>

    <h2>Revenue by channel</h2>
    @if ($channels === [])
        <p class="muted">No channel sales in this range.</p>
    @else
        <ul class="list">
            @foreach ($channels as $row)
                <li>
                    <span>{{ $row['label'] }}</span>
                    <strong>{{ $money($row['amount']) }} · {{ number_format($row['width'], 1) }}%</strong>
                </li>
            @endforeach
        </ul>
    @endif

    <h2>Time of day</h2>
    @if ($dayparts === [])
        <p class="muted">No day-part sales in this range.</p>
    @else
        <ul class="list">
            @foreach ($dayparts as $row)
                <li>
                    <span>{{ $row['label'] }}</span>
                    <strong>{{ $money($row['amount']) }} · {{ number_format($row['width'], 1) }}%</strong>
                </li>
            @endforeach
        </ul>
    @endif

    <h2>Branch performance</h2>
    @if ($branches === [])
        <p class="muted">No sales in this range.</p>
    @else
        <ul class="list">
            @foreach ($branches as $row)
                <li>
                    <span>{{ $row['label'] }}</span>
                    <strong>{{ $money($row['amount']) }} · {{ number_format($row['width'], 1) }}%</strong>
                </li>
            @endforeach
        </ul>
    @endif

    <h2>Top products</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th class="num">Qty</th>
                <th class="num">Revenue</th>
                <th class="num">COGS</th>
                <th class="num">Profit</th>
                <th class="num">Margin</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $index => $product)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $product['name'] }}
                        @if ($product['sku'])
                            <span class="muted">· {{ $product['sku'] }}</span>
                        @endif
                    </td>
                    <td class="num">{{ number_format($product['qty']) }}</td>
                    <td class="num">{{ $money($product['revenue']) }}</td>
                    <td class="num">{{ $money($product['cogs']) }}</td>
                    <td class="num">{{ $signed($product['profit']) }}</td>
                    <td class="num">{{ $product['margin'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="7">No sales in this range.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
