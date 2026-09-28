@extends('layouts.app')

@section('title', 'Subscriptions | SalesDock')

@php
    $cycles = [
        'MONTHLY' => 'Monthly',
        'QUARTERLY' => 'Quarterly',
        'ANNUALLY' => 'Annually',
    ];
    $labels = [
        'TRIALING' => 'Trialing',
        'ACTIVE' => 'Active',
        'PAST_DUE' => 'Past due',
        'SUSPENDED' => 'Suspended',
        'CANCELLED' => 'Cancelled',
        'EXPIRED' => 'Expired',
    ];
    $tone = [
        'ACTIVE' => 'active',
        'TRIALING' => 'pending',
        'PAST_DUE' => 'pending',
        'SUSPENDED' => 'overdue',
        'CANCELLED' => 'cancelled',
        'EXPIRED' => 'cancelled',
    ];
    $when = fn ($date) => $date?->timezone('Africa/Lagos')->format('d M Y') ?: '—';
@endphp

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Subscriptions</h1>
                <span class="tally">{{ number_format($total) }} total</span>
            </div>
            <x-ui.filter :action="route('admin.subscriptions')" :active="$filtered" label="Filter subscriptions">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Shop name or email" maxlength="80">
                </div>
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'ACTIVE' => 'Active', 'TRIALING' => 'Trialing', 'PAST_DUE' => 'Past due', 'SUSPENDED' => 'Suspended', 'CANCELLED' => 'Cancelled', 'EXPIRED' => 'Expired']" />
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Shop</th>
                        <th>Plan</th>
                        <th>Cycle</th>
                        <th>Status</th>
                        <th>Renews</th>
                        <th>Started</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscriptions as $subscription)
                        <tr>
                            <td>
                                @if ($subscription->tenant)
                                    <a href="{{ route('admin.tenants.show', $subscription->tenant) }}">{{ $subscription->tenant->name }}</a>
                                    @if ($subscription->tenant->email)
                                        <div class="muted">{{ $subscription->tenant->email }}</div>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $subscription->plan?->name ?: '—' }}</td>
                            <td>{{ $cycles[$subscription->billingCycle] ?? $subscription->billingCycle }}</td>
                            <td><span class="badge {{ $tone[$subscription->status] ?? 'draft' }}">{{ $labels[$subscription->status] ?? $subscription->status }}</span></td>
                            <td>{{ $when($subscription->currentPeriodEnd) }}</td>
                            <td>{{ $when($subscription->createdAt) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="6">No subscriptions.</td>
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
