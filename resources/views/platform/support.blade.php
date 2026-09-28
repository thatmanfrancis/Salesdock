@extends('layouts.app')

@section('title', 'Support | SalesDock')

@php
    $statusLabels = ['OPEN' => 'Open', 'IN_PROGRESS' => 'In Progress', 'RESOLVED' => 'Resolved', 'CLOSED' => 'Closed'];
    $statusTones  = ['OPEN' => 'pending', 'IN_PROGRESS' => 'active', 'RESOLVED' => 'approved', 'CLOSED' => 'draft'];
    $priorityTones = ['LOW' => 'draft', 'NORMAL' => '', 'HIGH' => 'pending', 'URGENT' => 'cancelled'];
@endphp

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Support</h1>
                <span class="tally">{{ number_format($counts->sum()) }} total</span>
            </div>
            <x-ui.filter :action="route('admin.support')" :active="$filtered" label="Filter tickets">
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All'] + $statusLabels" />
            </x-ui.filter>
        </div>

        <div class="stat-row" style="margin-bottom:1rem">
            @foreach ($statusLabels as $key => $label)
                <a href="{{ route('admin.support', ['status' => $key]) }}" class="stat-pill @if ($status === $key) on @endif">
                    <span>{{ $label }}</span>
                    <strong>{{ number_format($counts[$key] ?? 0) }}</strong>
                </a>
            @endforeach
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Shop</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Opened</th>
                        <th>Last update</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td><a href="{{ route('admin.support.show', $ticket) }}">{{ $ticket->subject }}</a></td>
                            <td>{{ $ticket->tenant?->name ?? '—' }}</td>
                            <td><span class="badge {{ $priorityTones[$ticket->priority] ?? '' }}">{{ ucfirst(strtolower($ticket->priority)) }}</span></td>
                            <td><span class="badge {{ $statusTones[$ticket->status] ?? 'draft' }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span></td>
                            <td>{{ $ticket->createdAt?->timezone('Africa/Lagos')->format('d M Y') }}</td>
                            <td>{{ $ticket->updatedAt?->timezone('Africa/Lagos')->format('d M Y, H:i') }}</td>
                            <td><a class="detail" href="{{ route('admin.support.show', $ticket) }}">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="7">No tickets.</td>
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
