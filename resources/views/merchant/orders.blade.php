@extends('layouts.app')

@section('title', 'Orders | SalesDock')

@section('content')
    <div class="page">
        <div class="page-tools">
            <x-ui.filter :action="route('orders')" :active="$filtered" label="Filter orders">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Ref, customer, or phone">
                </div>
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All statuses', 'PENDING' => 'Pending', 'RESERVED' => 'Reserved', 'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled', 'RETURNED' => 'Returned']" />
                @if ($shop)
                    <x-ui.select name="channel" label="Channel" :value="$channel" :options="['' => 'All channels', 'POS' => 'POS', 'ONLINE' => 'Online', 'WHATSAPP' => 'WhatsApp', 'INSTAGRAM' => 'Instagram', 'LINK' => 'Link']" />
                    <x-ui.select name="cashier" label="Cashier" :value="$cashier" :options="['' => 'All cashiers'] + $names->all()" />
                @endif
                <div class="ui-filter-dates">
                    <x-ui.date name="from" label="From" :value="$from" />
                    <x-ui.date name="to" label="To" :value="$to" />
                </div>
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Status</th>
                        @if ($shop)
                            <th>Channel</th>
                            <th>Cashier</th>
                        @endif
                        <th>Customer</th>
                        <th>Net</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>{{ $order->createdAt?->format('d M H:i') }}</td>
                            <td>{{ $order->status }}</td>
                            @if ($shop)
                                <td>{{ $order->channel }}</td>
                                <td>{{ $names[$order->cashierId] ?? '—' }}</td>
                            @endif
                            <td><a href="{{ route('orders.show', $order) }}">{{ $order->customerName ?: 'Walk-in' }}</a></td>
                            <td>{{ $order->netAmount !== null ? '₦'.number_format((float) $order->netAmount, 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="{{ $shop ? 6 : 4 }}">No orders found.</td>
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
