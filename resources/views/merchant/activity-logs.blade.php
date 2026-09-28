@extends('layouts.app')

@section('title', 'Activity logs | SalesDock')

@section('content')
    <div class="page">
        <div class="page-tools">
            <x-ui.filter :action="route('activity-logs')" :active="$filtered" label="Filter activity logs">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Description" maxlength="80">
                </div>
                <x-ui.select name="module" label="Module" :value="$module" :options="$modules" />
                <x-ui.select name="person" label="Person" :value="$person" :options="$people" />
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
                        <th>Time</th>
                        <th>Person</th>
                        <th>Role</th>
                        <th>Module</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->timestamp?->timezone('Africa/Lagos')->format('d M Y, H:i') ?: '—' }}</td>
                            <td>{{ $log->user?->name ?: '—' }}</td>
                            <td>{{ $roles[$log->role] ?? ($log->role ?: '—') }}</td>
                            <td><span class="pill">{{ $labels[$log->module] ?? \Illuminate\Support\Str::headline((string) $log->module) }}</span></td>
                            <td>{{ $log->description }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="5">No activity logs.</td>
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
