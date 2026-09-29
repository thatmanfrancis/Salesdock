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
@endsection
