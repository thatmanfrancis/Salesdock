@extends('layouts.app')

@section('title', 'Check-ins | SalesDock')

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Check-ins</h1>
                <span class="tally">{{ number_format($total) }} total</span>
            </div>
            <x-ui.filter :action="route('admin.check-ins')" :active="$filtered" label="Filter check-ins">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Business, person or reason" maxlength="80">
                </div>
                <x-ui.select name="reason" label="Reason" :value="$withReason ? '1' : ''" :options="['' => 'All', '1' => 'Has a reason']" />
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Business</th>
                        <th>Person</th>
                        <th>Days away</th>
                        <th>Reason</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($checkIns as $checkIn)
                        <tr>
                            <td>
                                @if ($checkIn->tenant)
                                    <a href="{{ route('admin.tenants.show', $checkIn->tenant) }}">{{ $checkIn->tenant->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $checkIn->user?->name ?: '—' }}<br><span class="muted">{{ $checkIn->user?->email }}</span></td>
                            <td>{{ number_format($checkIn->daysAway) }}</td>
                            <td>{{ $checkIn->reason ?: '—' }}</td>
                            <td>{{ $checkIn->createdAt?->timezone('Africa/Lagos')->format('d M Y, H:i') ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="5">No check-ins yet.</td>
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
