@extends('layouts.app')

@section('title', 'Customers | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
@endphp

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Customers</h1>
                <span class="tally">{{ number_format($total) }} total</span>
            </div>
            <x-ui.filter :action="route('admin.customers')" :active="$filtered" label="Filter customers">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Name, phone, or email" maxlength="80">
                </div>
                <x-ui.select name="shop" label="Shop" :value="$shop" :options="$shops" />
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th class="num">Orders</th>
                        <th class="num">Spend</th>
                        <th class="num">Points</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @php $group = null; @endphp
                    @forelse ($customers as $customer)
                        @if ($group !== ($customer->tenantId ?? 'platform'))
                            @php $group = $customer->tenantId ?? 'platform'; @endphp
                            <tr class="group">
                                <td colspan="7">
                                    @if ($customer->tenant)
                                        <a href="{{ route('admin.tenants.show', $customer->tenant) }}">{{ $customer->tenant->name }}</a>
                                        <span class="muted">{{ $customer->tenant->slug }}</span>
                                    @else
                                        Platform
                                    @endif
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->phone ?: '—' }}</td>
                            <td>{{ $customer->email ?: '—' }}</td>
                            <td class="num">{{ number_format($customer->totalOrders) }}</td>
                            <td class="num">{{ $money($customer->totalSpend) }}</td>
                            <td class="num">{{ number_format($customer->loyaltyPoints) }}</td>
                            <td><span class="badge {{ $customer->isActive ? 'active' : 'draft' }}">{{ $customer->isActive ? 'Active' : 'Inactive' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="7">No customers.</td>
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
