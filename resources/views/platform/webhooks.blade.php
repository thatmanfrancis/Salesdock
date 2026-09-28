@extends('layouts.app')

@section('title', 'Webhooks | SalesDock')

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Webhooks</h1>
                <span class="tally">{{ number_format($total) }} total</span>
            </div>
            <x-ui.filter :action="route('admin.webhooks')" :active="$filtered" label="Filter webhooks">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Reference or event" maxlength="80">
                </div>
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'processed' => 'Processed', 'pending' => 'Pending', 'failed' => 'Failed']" />
            </x-ui.filter>
        </div>

        <div class="ledger-line tri">
            @foreach ($cards as $card)
                <article>
                    <span>{{ $card['label'] }}</span>
                    <strong @class([$card['class'] ?? ''])>{{ $card['value'] }}</strong>
                </article>
            @endforeach
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Gateway</th>
                        <th>Event</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Error</th>
                        <th>Received</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr @class(['late' => filled($event->error)])>
                            <td><span class="pill">{{ $event->gateway }}</span></td>
                            <td>{{ $event->eventType }}</td>
                            <td>{{ $event->reference }}</td>
                            <td>
                                @if (filled($event->error))
                                    <span class="badge overdue">Failed</span>
                                @elseif ($event->processed)
                                    <span class="badge filed">Processed</span>
                                @else
                                    <span class="badge pending">Pending</span>
                                @endif
                            </td>
                            <td>{{ $event->error ?: '—' }}</td>
                            <td>{{ $event->createdAt?->timezone('Africa/Lagos')->format('d M Y, H:i') ?: '—' }}</td>
                            <td><button type="button" class="detail" data-detail aria-expanded="false" aria-controls="detail-{{ $event->id }}">Details</button></td>
                        </tr>
                        <tr class="detail" id="detail-{{ $event->id }}" hidden>
                            <td colspan="7">
                                <pre class="payload">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="7">No webhooks.</td>
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
