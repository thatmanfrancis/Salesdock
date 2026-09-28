@extends('layouts.app')

@section('title', 'Support | SalesDock')

@php
    $statusLabels = ['OPEN' => 'Open', 'IN_PROGRESS' => 'In Progress', 'RESOLVED' => 'Resolved', 'CLOSED' => 'Closed'];
    $statusTones  = ['OPEN' => 'pending', 'IN_PROGRESS' => 'active', 'RESOLVED' => 'approved', 'CLOSED' => 'draft'];
    $creating     = old('form') === 'create' && $errors->any();
@endphp

@section('content')
    <div class="page">
        <div class="page-tools">
            <button type="button" data-ticket-open>New ticket</button>
            <x-ui.filter :action="route('support')" :active="$filtered" label="Filter tickets">
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All'] + $statusLabels" />
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
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
                            <td><a href="{{ route('support.show', $ticket) }}">{{ $ticket->subject }}</a></td>
                            <td>{{ ucfirst(strtolower($ticket->priority)) }}</td>
                            <td><span class="badge {{ $statusTones[$ticket->status] ?? 'draft' }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span></td>
                            <td>{{ $ticket->createdAt?->timezone('Africa/Lagos')->format('d M Y') }}</td>
                            <td>{{ $ticket->updatedAt?->timezone('Africa/Lagos')->format('d M Y, H:i') }}</td>
                            <td>
                                <a class="detail" href="{{ route('support.show', $ticket) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="6">No support tickets yet.</td>
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

    <div class="ui-modal" data-ticket-modal @unless ($creating) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('support.store') }}" data-busy>
            @csrf
            <input type="hidden" name="form" value="create">
            <div class="ui-filter-head">
                <strong>New support ticket</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <div class="ui-field">
                <span>Subject <span class="req">*</span></span>
                <input type="text" name="subject" value="{{ $creating ? old('subject') : '' }}" required maxlength="255" placeholder="Brief description of your issue">
            </div>
            <x-ui.select name="priority" label="Priority" :value="$creating ? old('priority', 'NORMAL') : 'NORMAL'" :options="['LOW' => 'Low', 'NORMAL' => 'Normal', 'HIGH' => 'High', 'URGENT' => 'Urgent']" />
            <div class="ui-field">
                <span>Message <span class="req">*</span></span>
                <textarea name="message" rows="5" required maxlength="5000" placeholder="Describe your issue in detail…">{{ $creating ? old('message') : '' }}</textarea>
            </div>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Submitting…"><span data-label>Submit ticket</span></button>
            </div>
        </form>
    </div>

    <script>
        const ticketModal = document.querySelector('[data-ticket-modal]');
        const closeModal  = () => { if (!ticketModal.querySelector('button.is-busy')) ticketModal.hidden = true; };
        document.querySelector('[data-ticket-open]').addEventListener('click', () => { ticketModal.hidden = false; });
        ticketModal.querySelectorAll('[data-close]').forEach((btn) => btn.addEventListener('click', closeModal));
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
