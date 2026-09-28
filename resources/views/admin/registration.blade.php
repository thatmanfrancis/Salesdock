@extends('layouts.app')

@section('title', $registration->businessName.' | SalesDock')

@php
    $show = fn ($value) => $value ?: '—';
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
    $site = trim((string) $registration->website);
    $href = $site === '' ? null : (preg_match('/^https?:\/\//i', $site) ? $site : 'https://'.$site);
@endphp

@section('content')
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('admin.registrations') }}">Back</a>
            <strong>{{ $registration->businessName }}</strong>
            <span class="badge {{ $tone[$registration->status] ?? 'draft' }}">{{ $labels[$registration->status] ?? $registration->status }}</span>
            <span class="pill">{{ $registration->emailVerified ? 'Email verified' : 'Email unverified' }}</span>
            <div class="head-actions">
                @if ($registration->status === 'PENDING')
                    @if ($registration->emailVerified)
                        <button type="button" data-ask data-url="{{ route('admin.registrations.approve', $registration) }}" data-title="Approve shop" data-copy="Approve {{ $registration->businessName }}?" data-label="Approve" data-loading="Approving…">Approve</button>
                    @else
                        <button type="button" disabled title="Email not verified">Approve</button>
                    @endif
                    <button type="button" class="quiet" data-reject data-url="{{ route('admin.registrations.reject', $registration) }}" data-copy="Reject {{ $registration->businessName }}?">Reject</button>
                @elseif ($registration->status === 'APPROVED' && $registration->planSelected)
                    <span class="badge active">Plan paid</span>
                @elseif ($registration->status === 'APPROVED')
                    <span class="badge pending">Awaiting payment</span>
                    <button type="button" data-ask data-url="{{ route('admin.registrations.resend', $registration) }}" data-title="Resend plan link" data-copy="Send the plan link to {{ $registration->email }} again?" data-label="Send" data-loading="Sending…">Resend plan link</button>
                @endif
            </div>
        </div>

        <section class="card">
            <p class="kicker band">Business</p>
            <div class="supplier-facts">
                <div><span>Owner</span><strong>{{ $registration->ownerName }}</strong></div>
                <div><span>Submitted</span><strong>{{ $registration->createdAt?->timezone('Africa/Lagos')->format('d M Y, H:i') ?: '—' }}</strong></div>
                <div><span>Type</span><strong>{{ $show($registration->businessType) }}</strong></div>
                <div><span>RC number</span><strong>{{ $show($registration->rcNumber) }}</strong></div>
                <div><span>TIN</span><strong>{{ $show($registration->tin) }}</strong></div>
                <div>
                    <span>Website</span>
                    <strong>
                        @if ($href)
                            <a href="{{ $href }}" target="_blank" rel="noreferrer">{{ $site }}</a>
                        @else
                            —
                        @endif
                    </strong>
                </div>
            </div>
        </section>

        <section class="card">
            <p class="kicker band">Contact</p>
            <div class="supplier-facts">
                <div><span>Email</span><strong>{{ $registration->email }}</strong></div>
                <div><span>Phone</span><strong>{{ $show($registration->phone) }}</strong></div>
                <div><span>City</span><strong>{{ $show($registration->city) }}</strong></div>
                <div class="wide"><span>Address</span><strong>{{ $show($registration->address) }}</strong></div>
            </div>
        </section>

        @if ($registration->message)
            <section class="card">
                <p class="kicker band">Message</p>
                <p class="note">{{ $registration->message }}</p>
            </section>
        @endif

        @if ($registration->rejectionReason)
            <section class="card">
                <p class="kicker band">Rejection</p>
                <p class="note">{{ $registration->rejectionReason }}</p>
            </section>
        @endif
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
                <button type="submit" data-loading="Saving…"><span data-label>Yes</span></button>
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
                askSubmit.classList.toggle('danger', button.dataset.tone === 'danger');
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
