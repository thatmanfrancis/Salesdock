@extends('layouts.app')

@section('title', 'Revenue | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
@endphp

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Revenue</h1>
                <span class="tally">{{ number_format($total) }} shops</span>
            </div>
            <x-ui.filter :action="route('admin.revenue')" :active="$filtered" label="Filter revenue">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Shop name or slug" maxlength="80">
                </div>
            </x-ui.filter>
        </div>

        <div class="ledger-line wide">
            @foreach ($cards as $card)
                <article>
                    <span>{{ $card['label'] }}</span>
                    <strong @class([$card['class'] ?? '']) title="{{ $card['value'] }}">{{ $card['value'] }}</strong>
                </article>
            @endforeach
        </div>

        <section class="card">
            <h2>Gross sales · last 6 months</h2>
            <div class="day-chart platform">
                @foreach ($chart as $bar)
                    <div>
                        <i style="height: {{ $bar['height'] }}%" title="{{ $bar['title'] }}"></i>
                        <span>{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Shop</th>
                        <th class="num">Gross</th>
                        <th class="num">Net</th>
                        <th class="num">VAT</th>
                        <th class="num">Profit</th>
                        <th class="num">Orders</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shops as $shop)
                        <tr>
                            <td>
                                <a href="{{ route('admin.tenants.show', $shop->id) }}">{{ $shop->name }}</a>
                                <div class="muted">{{ $shop->slug }}</div>
                            </td>
                            <td class="num">{{ $money($shop->gross) }}</td>
                            <td class="num">{{ $money($shop->net) }}</td>
                            <td class="num vat">{{ $money($shop->vat) }}</td>
                            <td class="num profit">{{ $money($shop->profit) }}</td>
                            <td class="num">{{ number_format($shop->orders) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="6">No revenue.</td>
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
