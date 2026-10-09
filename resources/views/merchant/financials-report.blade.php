@extends('merchant.report')

@section('body')
    @php
        $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
        $channelLabel = [
            'POS' => 'POS',
            'ONLINE' => 'Online',
            'WHATSAPP' => 'WhatsApp',
            'INSTAGRAM' => 'Instagram',
            'LINK' => 'Link',
        ];
    @endphp

    <div class="totals">
        @foreach ($summary as $stat)
            <div><span>{{ $stat['label'] }}</span><strong>{{ $stat['value'] }}</strong></div>
        @endforeach
    </div>

    @if ($filters !== [])
        <p class="muted">Filters: {{ implode(' · ', $filters) }}</p>
    @endif

    <h2>Ledger entries</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Order</th>
                <th>Channel</th>
                <th>Cashier</th>
                <th class="num">Gross</th>
                <th class="num">VAT</th>
                <th class="num">COGS</th>
                <th class="num">Profit</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->createdAt?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</td>
                    <td>{{ strtoupper(substr((string) $entry->orderId, -8)) }}</td>
                    <td>{{ $channelLabel[$entry->channel] ?? ($entry->channel ?: '—') }}</td>
                    <td>{{ $entry->cashier?->name ?: '—' }}</td>
                    <td class="num">{{ $money($entry->grossRevenue) }}</td>
                    <td class="num">{{ $money($entry->totalVat) }}</td>
                    <td class="num">{{ $money($entry->totalCogs) }}</td>
                    <td class="num">{{ $money($entry->grossProfit) }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No ledger entries.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Operating expenses</h2>
    @if ($filters !== [])
        <p class="muted">Expenses follow the date range only; channel and cashier filters apply to sales.</p>
    @endif
    @if ($spendByCategory->isNotEmpty())
        <div class="totals">
            @foreach ($spendByCategory as $row)
                <div><span>{{ $expenseLabels[$row->category] ?? $row->category }}</span><strong>{{ $money($row->total) }}</strong></div>
            @endforeach
        </div>
    @endif
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Expense</th>
                <th>Category</th>
                <th>Payment</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expenses as $expense)
                <tr>
                    <td>{{ $expense->date?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</td>
                    <td>
                        {{ $expense->title }}
                        @foreach ($expense->items as $item)
                            <div class="muted">{{ $item->name }}{{ $item->quantity ? ' · '.rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.').($item->unit ? ' '.$item->unit : '') : '' }} — {{ $money($item->amount) }}</div>
                        @endforeach
                    </td>
                    <td>{{ $expenseLabels[$expense->category] ?? $expense->category }}</td>
                    <td>{{ $expenseMethods[$expense->paymentMethod ?? ''] ?? '—' }}</td>
                    <td class="num">{{ $money($expense->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No expenses logged.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
