@extends('layouts.app')

@section('title', 'Tax filing '.$filing->period.' | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $label = \Illuminate\Support\Carbon::parse($filing->period.'-01')->format('F Y');
@endphp

@section('content')
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('tax-filings', ['year' => substr($filing->period, 0, 4)]) }}">Back</a>
            <strong>{{ $label }}</strong>
            <span class="badge {{ strtolower($filing->status) }}">{{ ucfirst(strtolower($filing->status)) }}</span>
            @if ($filing->submissionRef)
                <span class="muted">FIRS {{ $filing->submissionRef }}</span>
            @endif
        </div>

        <div class="ledger-line">
            <article>
                <span>Gross sales</span>
                <strong title="{{ $money($totals['gross']) }}">{{ $money($totals['gross']) }}</strong>
            </article>
            <article>
                <span>Sales</span>
                <strong>{{ number_format($totals['sales']) }}</strong>
            </article>
            <article>
                <span>Total VAT</span>
                <strong title="{{ $money($totals['vat']) }}">{{ $money($totals['vat']) }}</strong>
            </article>
            <article>
                <span>Net sales</span>
                <strong title="{{ $money($totals['net']) }}">{{ $money($totals['net']) }}</strong>
            </article>
        </div>

        <div class="page-tools">
            <x-ui.filter :action="route('tax-filings.show', $filing)" :active="$filtered" label="Filter sales">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Order reference">
                </div>
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Payment</th>
                        <th class="num">Gross</th>
                        <th class="num">VAT</th>
                        <th class="num">Net</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td><span class="ref">{{ strtoupper(substr((string) $entry->orderId, -8)) }}</span></td>
                            <td>{{ $entry->createdAt?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</td>
                            <td>{{ $entry->paymentMethod ? ucwords(strtolower(str_replace('_', ' ', $entry->paymentMethod))) : '—' }}</td>
                            <td class="num">{{ $money($entry->grossRevenue) }}</td>
                            <td class="num vat">{{ $money($entry->totalVat) }}</td>
                            <td class="num profit">{{ $money($entry->netRevenue) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="6">{{ $q !== '' ? 'No sales found.' : 'No sales in this month.' }}</td>
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
