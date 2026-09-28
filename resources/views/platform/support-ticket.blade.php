@extends('layouts.app')

@section('title', 'Ticket | SalesDock')

@php
    $statusLabels = ['OPEN' => 'Open', 'IN_PROGRESS' => 'In Progress', 'RESOLVED' => 'Resolved', 'CLOSED' => 'Closed'];
    $statusTones  = ['OPEN' => 'pending', 'IN_PROGRESS' => 'active', 'RESOLVED' => 'approved', 'CLOSED' => 'draft'];
@endphp

@section('content')
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('admin.support') }}">Back</a>
            <strong>{{ $ticket->subject }}</strong>
            <span class="badge {{ $statusTones[$ticket->status] ?? 'draft' }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span>
            <span class="pill">{{ ucfirst(strtolower($ticket->priority)) }}</span>
            <div class="head-actions">
                @if ($ticket->status === 'OPEN')
                    <form method="post" action="{{ route('admin.support.claim', $ticket) }}">
                        @csrf
                        <button type="submit">Claim ticket</button>
                    </form>
                @endif
                @if ($ticket->isOpen())
                    <form method="post" action="{{ route('admin.support.resolve', $ticket) }}">
                        @csrf
                        <button type="submit" class="quiet">Mark resolved</button>
                    </form>
                @endif
            </div>
        </div>

        <section class="card">
            <p class="kicker band">Details</p>
            <div class="supplier-facts">
                <div><span>Shop</span><strong>{{ $ticket->tenant?->name ?? '—' }}</strong></div>
                <div><span>Submitted by</span><strong>{{ $ticket->user?->name ?? '—' }}</strong></div>
                <div><span>Email</span><strong>{{ $ticket->user?->email ?? '—' }}</strong></div>
                <div><span>Opened</span><strong>{{ $ticket->createdAt?->timezone('Africa/Lagos')->format('d M Y, H:i') }}</strong></div>
                @if ($ticket->resolvedAt)
                    <div><span>Resolved</span><strong>{{ $ticket->resolvedAt->timezone('Africa/Lagos')->format('d M Y, H:i') }}</strong></div>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="support-thread">
                {{-- Original message --}}
                <div class="support-msg support-msg--us">
                    <div class="support-msg-meta">
                        <strong>{{ $ticket->user?->name ?? 'Merchant' }}</strong>
                        <time>{{ $ticket->createdAt?->timezone('Africa/Lagos')->format('d M Y, H:i') }}</time>
                    </div>
                    <div class="support-msg-body">{{ $ticket->message }}</div>
                </div>

                @foreach ($replies as $reply)
                    <div class="support-msg {{ $reply->isAdmin ? 'support-msg--admin' : 'support-msg--us' }}">
                        <div class="support-msg-meta">
                            <strong>{{ $reply->isAdmin ? 'SalesDock Support' : ($reply->user?->name ?? 'Merchant') }}</strong>
                            <time>{{ $reply->createdAt?->timezone('Africa/Lagos')->format('d M Y, H:i') }}</time>
                        </div>
                        <div class="support-msg-body">{{ $reply->message }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($ticket->isOpen())
            <section class="card">
                <p class="kicker">Reply to merchant</p>
                <form method="post" action="{{ route('admin.support.reply', $ticket) }}" data-busy>
                    @csrf
                    <div class="ui-field">
                        <textarea name="message" rows="4" required maxlength="5000" placeholder="Write your reply… (will be emailed to the merchant)"></textarea>
                    </div>
                    <div class="ui-modal-grid" style="margin-top:0.65rem">
                        <x-ui.select name="status" label="Update status" :value="''" :options="['' => 'Keep current', 'IN_PROGRESS' => 'In Progress', 'RESOLVED' => 'Mark resolved', 'CLOSED' => 'Close ticket']" />
                    </div>
                    <div style="margin-top:0.75rem">
                        <button type="submit" data-loading="Sending…"><span data-label>Send reply</span></button>
                    </div>
                </form>
            </section>
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
    </script>
@endsection
