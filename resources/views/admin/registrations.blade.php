@extends('layouts.app')

@section('title', 'Registrations | SalesDock')

@php
    $tone = [
        'APPROVED' => 'approved',
        'PENDING' => 'pending',
        'REJECTED' => 'rejected',
    ];
    $labels = [
        'APPROVED' => 'Approved',
        'PENDING' => 'Pending',
        'REJECTED' => 'Rejected',
    ];
@endphp

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Registrations</h1>
                <span class="tally">{{ number_format($total) }} total</span>
            </div>
            <x-ui.filter :action="route('admin.registrations')" :active="$filtered" label="Filter registrations">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Business, owner, or email" maxlength="80">
                </div>
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'PENDING' => 'Pending', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected']" />
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Business</th>
                        <th>Owner</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registrations as $registration)
                        <tr>
                            <td><a href="{{ route('admin.registrations.show', $registration) }}">{{ $registration->businessName }}</a></td>
                            <td>{{ $registration->ownerName }}</td>
                            <td>{{ $registration->email }}</td>
                            <td>
                                <span class="badge {{ $tone[$registration->status] ?? 'draft' }}">{{ $labels[$registration->status] ?? $registration->status }}</span>
                                @unless ($registration->emailVerified)
                                    <span class="pill">Email unverified</span>
                                @endunless
                            </td>
                            <td>{{ $registration->createdAt?->timezone('Africa/Lagos')->format('d M Y, H:i') ?: '—' }}</td>
                            <td>
                                @if ($registration->status === 'PENDING')
                                    <div class="row-actions">
                                        @if ($registration->emailVerified)
                                            <button type="button" data-ask data-url="{{ route('admin.registrations.approve', $registration) }}" data-title="Approve shop" data-copy="Approve {{ $registration->businessName }}?" data-label="Approve" data-loading="Approving…">Approve</button>
                                        @else
                                            <button type="button" disabled title="Email not verified">Approve</button>
                                        @endif
                                        <button type="button" class="quiet" data-reject data-url="{{ route('admin.registrations.reject', $registration) }}" data-copy="Reject {{ $registration->businessName }}?">Reject</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="6">No registrations.</td>
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

    <div class="ui-modal" data-ask-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('admin.registrations') }}" data-busy>
            @csrf
            <div class="ui-filter-head">
                <strong data-ask-title>Are you sure?</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask" data-ask-copy></p>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Approving…"><span data-label>Approve</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-reject-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('admin.registrations') }}" data-busy>
            @csrf
            <div class="ui-filter-head">
                <strong>Reject shop</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask" data-reject-copy></p>
            <label>
                <span>Reason</span>
                <textarea name="reason" rows="3" maxlength="500" placeholder="Optional" data-reject-reason></textarea>
            </label>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" class="danger" data-loading="Rejecting…"><span data-label>Reject</span></button>
            </div>
        </form>
    </div>

    <script>
        const askModal = document.querySelector('[data-ask-modal]');
        const askForm = askModal.querySelector('form');
        const askTitle = askModal.querySelector('[data-ask-title]');
        const askCopy = askModal.querySelector('[data-ask-copy]');
        const askSubmit = askForm.querySelector('[type="submit"]');
        const askLabel = askSubmit.querySelector('[data-label]');
        const rejectModal = document.querySelector('[data-reject-modal]');
        const rejectForm = rejectModal.querySelector('form');
        const rejectCopy = rejectModal.querySelector('[data-reject-copy]');
        const rejectReason = rejectModal.querySelector('[data-reject-reason]');
        const rejectSubmit = rejectForm.querySelector('[type="submit"]');
        const closeAsk = () => { if (!askSubmit.classList.contains('is-busy')) askModal.hidden = true; };
        const closeReject = () => { if (!rejectSubmit.classList.contains('is-busy')) rejectModal.hidden = true; };
        document.querySelectorAll('[data-ask]').forEach((button) => {
            button.addEventListener('click', () => {
                askForm.action = button.dataset.url;
                askTitle.textContent = button.dataset.title || 'Are you sure?';
                askCopy.textContent = button.dataset.copy || '';
                askLabel.textContent = button.dataset.label || 'Yes';
                askSubmit.dataset.loading = button.dataset.loading || 'Saving…';
                askModal.hidden = false;
            });
        });
        document.querySelectorAll('[data-reject]').forEach((button) => {
            button.addEventListener('click', () => {
                rejectForm.action = button.dataset.url;
                rejectCopy.textContent = button.dataset.copy || '';
                rejectReason.value = '';
                rejectModal.hidden = false;
            });
        });
        askModal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', closeAsk));
        rejectModal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', closeReject));
        [askForm, rejectForm].forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('[type="submit"]');
                const label = button.querySelector('[data-label]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                if (label) label.textContent = button.dataset.loading || 'Saving…';
            });
        });
    </script>
@endsection
