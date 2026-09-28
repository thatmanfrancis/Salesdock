@extends('layouts.app')

@section('title', 'Audit logs | SalesDock')

@php
    $facts = function ($details) {
        if (! is_array($details)) {
            return [];
        }
        $rows = [];
        foreach ($details as $key => $value) {
            if (is_bool($value)) {
                $shown = $value ? 'Yes' : 'No';
            } elseif (is_array($value)) {
                $shown = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—';
            } elseif ($value === null || $value === '') {
                $shown = '—';
            } else {
                $shown = (string) $value;
            }
            $rows[] = ['label' => \Illuminate\Support\Str::headline((string) $key), 'value' => $shown];
        }

        return $rows;
    };
@endphp

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Audit logs</h1>
                <span class="tally">{{ number_format($total) }} total</span>
            </div>
            <x-ui.filter :action="route('admin.audit-logs')" :active="$filtered" label="Filter audit logs">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Person or shop" maxlength="80">
                </div>
                <x-ui.select name="action" label="Action" :value="$action" :options="$actions" />
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
                        <th>Shop</th>
                        <th>Person</th>
                        <th>Action</th>
                        <th>Approved by</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        @php
                            $approved = $log->supervisor && $log->supervisorId !== $log->userId ? $log->supervisor->name : null;
                        @endphp
                        <tr>
                            <td>{{ $log->timestamp?->timezone('Africa/Lagos')->format('d M Y, H:i') ?: '—' }}</td>
                            <td>
                                @if ($log->tenant)
                                    <a href="{{ route('admin.tenants.show', $log->tenant) }}">{{ $log->tenant->name }}</a>
                                    <div class="muted">{{ $log->tenant->slug }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $log->user?->name ?: 'System' }}</td>
                            <td><span class="pill">{{ $labels[$log->action] ?? \Illuminate\Support\Str::headline((string) $log->action) }}</span></td>
                            <td>{{ $approved ?: '—' }}</td>
                            <td><button type="button" class="detail" data-detail aria-expanded="false" aria-controls="detail-{{ $log->id }}">Details</button></td>
                        </tr>
                        <tr class="detail" id="detail-{{ $log->id }}" hidden>
                            <td colspan="6">
                                <dl class="log-facts">
                                    @foreach ($facts($log->details) as $fact)
                                        <dt>{{ $fact['label'] }}</dt>
                                        <dd>{{ $fact['value'] }}</dd>
                                    @endforeach
                                    <dt>IP address</dt>
                                    <dd>{{ $log->ipAddress ?: '—' }}</dd>
                                </dl>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="6">No audit logs.</td>
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

    <script>
        document.querySelectorAll('[data-detail]').forEach((button) => {
            button.addEventListener('click', () => {
                const row = document.getElementById(button.getAttribute('aria-controls'));
                if (!row) return;
                row.hidden = !row.hidden;
                button.setAttribute('aria-expanded', row.hidden ? 'false' : 'true');
            });
        });
    </script>
@endsection
