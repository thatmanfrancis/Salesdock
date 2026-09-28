@extends('layouts.app')

@section('title', 'Ticket | SalesDock')

@php
    $statusLabels = ['OPEN' => 'Open', 'IN_PROGRESS' => 'In Progress', 'RESOLVED' => 'Resolved', 'CLOSED' => 'Closed'];
    $statusTones  = ['OPEN' => 'pending', 'IN_PROGRESS' => 'active', 'RESOLVED' => 'approved', 'CLOSED' => 'draft'];
    $lastReplyId  = $replies->last()?->id ?? '';
@endphp

@section('content')
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('support') }}">Back</a>
            <strong>{{ $ticket->subject }}</strong>
            <span class="badge {{ $statusTones[$ticket->status] ?? 'draft' }}" id="ticket-status-badge">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span>
            <span class="pill">{{ ucfirst(strtolower($ticket->priority)) }}</span>
            @if ($ticket->isOpen())
                <div class="head-actions">
                    <form method="post" action="{{ route('support.close', $ticket) }}">
                        @csrf
                        <button type="submit" class="quiet">Close ticket</button>
                    </form>
                </div>
            @endif
        </div>

        <section class="card">
            <div class="support-thread" id="support-thread">
                {{-- Original message --}}
                <div class="support-msg support-msg--us">
                    <div class="support-msg-meta">
                        <strong>{{ $ticket->user?->name ?? 'You' }}</strong>
                        <time>{{ $ticket->createdAt?->timezone('Africa/Lagos')->format('d M Y, H:i') }}</time>
                    </div>
                    <div class="support-msg-body">{{ $ticket->message }}</div>
                </div>

                @foreach ($replies as $reply)
                    <div class="support-msg {{ $reply->isAdmin ? 'support-msg--admin' : 'support-msg--us' }}" data-reply-id="{{ $reply->id }}">
                        <div class="support-msg-meta">
                            <strong>{{ $reply->isAdmin ? 'SalesDock Support' : ($reply->user?->name ?? 'You') }}</strong>
                            <time>{{ $reply->createdAt?->timezone('Africa/Lagos')->format('d M Y, H:i') }}</time>
                        </div>
                        <div class="support-msg-body">{{ $reply->message }}</div>
                    </div>
                @endforeach

                {{-- Live replies land here --}}
                <div id="live-replies"></div>

                @if ($ticket->isOpen())
                    <p class="support-live-indicator" id="live-indicator">
                        <span class="live-dot"></span> Connected — replies appear here in real time
                    </p>
                @endif
            </div>
        </section>

        @if ($ticket->isOpen())
            <section class="card">
                <p class="kicker">Reply</p>
                <form method="post" action="{{ route('support.reply', $ticket) }}" data-busy id="reply-form">
                    @csrf
                    <div class="ui-field">
                        <textarea name="message" rows="4" required maxlength="5000" placeholder="Write your reply…" id="reply-message"></textarea>
                    </div>
                    <div style="margin-top:0.5rem">
                        <button type="submit" data-loading="Sending…"><span data-label>Send reply</span></button>
                    </div>
                </form>
            </section>
        @else
            <p class="muted" style="padding:0 0.25rem">
                This ticket is {{ strtolower($statusLabels[$ticket->status] ?? $ticket->status) }}.
                <a href="{{ route('support') }}">Open a new ticket</a> if you need further help.
            </p>
        @endif
    </div>

    <script>
        document.querySelectorAll('form[data-busy]').forEach((form) => {
            form.addEventListener('submit', () => {
                const btn   = form.querySelector('[type="submit"]');
                const label = btn?.querySelector('[data-label]');
                if (!btn || btn.disabled) return;
                btn.disabled = true;
                btn.classList.add('is-busy');
                if (label) label.textContent = btn.dataset.loading || 'Saving…';
            });
        });

        @if ($ticket->isOpen())
        // ── SSE live replies ─────────────────────────────────────────────────
        const liveBox   = document.getElementById('live-replies');
        const thread    = document.getElementById('support-thread');
        const indicator = document.getElementById('live-indicator');

        let lastId = @json($lastReplyId);

        function addReply(data) {
            const wrap = document.createElement('div');
            wrap.className = 'support-msg support-msg--admin support-msg--new';
            wrap.dataset.replyId = data.id;
            wrap.innerHTML =
                '<div class="support-msg-meta">' +
                    '<strong>SalesDock Support</strong>' +
                    '<time>' + data.createdAt + '</time>' +
                '</div>' +
                '<div class="support-msg-body"></div>';
            wrap.querySelector('.support-msg-body').textContent = data.message;
            liveBox.appendChild(wrap);
            wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            // Flash the float button badge
            updateFloatBadge(1);
        }

        function connect() {
            const url = @json(route('support.stream', $ticket)) + '?lastId=' + encodeURIComponent(lastId);
            const es  = new EventSource(url);

            es.onmessage = (e) => {
                const data = JSON.parse(e.data);
                if (data.id) {
                    lastId = data.id;
                    addReply(data);
                }
            };

            es.addEventListener('reconnect', (e) => {
                const d = JSON.parse(e.data);
                if (d.lastId) lastId = d.lastId;
                es.close();
                setTimeout(connect, 1000);
            });

            es.addEventListener('closed', () => {
                es.close();
                if (indicator) {
                    indicator.innerHTML = '<span>This ticket has been closed.</span>';
                }
                const badge = document.getElementById('ticket-status-badge');
                if (badge) { badge.textContent = 'Closed'; badge.className = 'badge draft'; }
            });

            es.onerror = () => {
                es.close();
                // Reconnect after 5 s on error
                setTimeout(connect, 5000);
            };
        }

        connect();
        @endif
    </script>
@endsection
