@extends('layouts.app')

@section('title', 'Financials | SalesDock')

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

@section('content')
    <div class="page">
        <div class="page-tools">
            <a class="tool" href="{{ route('financials.pdf', request()->query()) }}" target="_blank" rel="noopener">Download PDF</a>
            <x-ui.filter :action="route('financials')" :active="$filtered" label="Filter ledger">
                <x-ui.select name="channel" label="Channel" :value="$channel" :options="$channels" />
                <x-ui.select name="cashier" label="Cashier" :value="$cashierId" :options="$cashiers" />
                <div class="ui-filter-dates">
                    <x-ui.date name="from" label="From" :value="$from" />
                    <x-ui.date name="to" label="To" :value="$to" />
                </div>
            </x-ui.filter>
        </div>

        <div class="ledger-line">
            @foreach ($summary as $stat)
                <article>
                    <span>{{ $stat['label'] }}</span>
                    <strong title="{{ $stat['value'] }}">{{ $stat['value'] }}</strong>
                </article>
            @endforeach
        </div>

        <section class="card">
            <h2>Gross profit · {{ $chart['range'] }}</h2>
            <div class="day-chart @if ($chart['wide']) wide @endif">
                @foreach ($chart['bars'] as $bar)
                    <div>
                        <i style="height: {{ $bar['height'] }}%" title="{{ $bar['value'] }}"></i>
                        <span>{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card orders">
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
                        @php
                            $ref = strtoupper(substr((string) $entry->orderId, -8));
                        @endphp
                        <tr>
                            <td>{{ $entry->createdAt?->format('d M Y') ?: '—' }}</td>
                            <td><a class="ref" href="{{ route('financials.show', $entry) }}">{{ $ref }}</a></td>
                            <td>{{ $channelLabel[$entry->channel] ?? ($entry->channel ?: '—') }}</td>
                            <td>{{ $entry->cashier?->name ?: '—' }}</td>
                            <td class="num">{{ $money($entry->grossRevenue) }}</td>
                            <td class="num vat">{{ $money($entry->totalVat) }}</td>
                            <td class="num">{{ $money($entry->totalCogs) }}</td>
                            <td class="num profit">{{ $money($entry->grossProfit) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="8">No ledger entries.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <nav class="pager" aria-label="Pages">
                @if ($page > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">Previous</a>
                @else
                    <span class="off">Previous</span>
                @endif
                <span>Page {{ $page }} of {{ $pages }}</span>
                @if ($page < $pages)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Next</a>
                @else
                    <span class="off">Next</span>
                @endif
            </nav>
        </section>
    </div>
@endsection
