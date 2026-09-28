@extends('layouts.app')

@section('title', 'Refunds | SalesDock')

@section('content')
    @php
        $asking = old('form') === 'request' && $errors->any();
        $columns = $canDecide ? 6 : 5;
    @endphp
    <div class="page">
        <div class="page-tools">
            <button type="button" data-refund-open>Request refund</button>
            <x-ui.filter :action="route('refunds')" :active="$filtered" label="Filter refunds">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Order reference or reason">
                </div>
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'PENDING' => 'Pending', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'PROCESSED' => 'Processed']" />
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Reason</th>
                        <th class="num">Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        @if ($canDecide)
                            <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($refunds as $refund)
                        @php
                            $sale = $sales[$refund->orderId] ?? null;
                            $ref = strtoupper(substr((string) ($sale ? ($sale->paymentRef ?: $sale->id) : $refund->orderId), -8));
                            $amount = '₦'.number_format((float) $refund->amount, 2);
                        @endphp
                        <tr>
                            <td>
                                @if ($sale)
                                    <a class="ref" href="{{ route('orders.show', $sale) }}">{{ $ref }}</a>
                                @else
                                    <span class="ref">{{ $ref }}</span>
                                @endif
                            </td>
                            <td class="clip" title="{{ $refund->reason }}">{{ $refund->reason }}</td>
                            <td class="num">{{ $amount }}</td>
                            <td><span class="badge {{ strtolower($refund->status) }}">{{ ucfirst(strtolower($refund->status)) }}</span></td>
                            <td>{{ $refund->createdAt?->format('d M Y') }}</td>
                            @if ($canDecide)
                                <td>
                                    <div class="refund-actions">
                                        @if ($refund->status === 'PENDING')
                                            <form method="post" action="{{ route('refunds.approve', $refund) }}" data-busy>
                                                @csrf
                                                <button type="submit" class="approve" data-loading="Approving…"><span data-label>Approve</span></button>
                                            </form>
                                            <button type="button" class="reject" data-decide data-url="{{ route('refunds.reject', $refund) }}" data-title="Reject refund" data-copy="Are you sure you want to reject the refund of {{ $amount }}?" data-submit="Reject" data-loading="Rejecting…" data-tone="danger">Reject</button>
                                        @elseif ($refund->status === 'APPROVED')
                                            <button type="button" class="process" data-decide data-url="{{ route('refunds.process', $refund) }}" data-title="Process refund" data-copy="Are you sure you want to mark the refund of {{ $amount }} as paid back?" data-submit="Process" data-loading="Processing…" data-tone="go">Process</button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="{{ $columns }}">No refunds found.</td>
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

    <div class="ui-modal" data-request-modal @unless ($asking) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('refunds.store') }}" data-busy>
            @csrf
            <input type="hidden" name="form" value="request">
            <div class="ui-filter-head">
                <strong>New refund request</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            @if (count($orderOptions['options']) < 2)
                <p class="ask">No completed sales can take a refund right now.</p>
            @endif
            <x-ui.select name="orderId" label="Order" :required="true" :value="$asking ? old('orderId', '') : ''" :options="$orderOptions['options']" />
            <label>
                <span>Amount (₦) <span class="req">*</span></span>
                <input type="number" name="amount" step="0.01" min="0.01" value="{{ $asking ? old('amount') : '' }}" placeholder="0.00" required data-amount>
            </label>
            <label>
                <span>Reason <span class="req">*</span></span>
                <input name="reason" value="{{ $asking ? old('reason') : '' }}" placeholder="Why this sale is being refunded" required maxlength="255">
            </label>
            <label>
                <span>Notes</span>
                <textarea name="notes" placeholder="Optional note">{{ $asking ? old('notes') : '' }}</textarea>
            </label>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Submitting…"><span data-label>Submit</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-decide-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('refunds.store') }}" data-busy data-decide-form>
            @csrf
            <div class="ui-filter-head">
                <strong data-decide-title>Are you sure?</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask" data-decide-copy>Are you sure?</p>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-decide-submit data-loading="Saving…"><span data-label>Confirm</span></button>
            </div>
        </form>
    </div>

    <script type="application/json" id="refund-caps">@json($orderOptions['caps'])</script>
    <script>
        const caps = JSON.parse(document.getElementById('refund-caps').textContent);
        const requestModal = document.querySelector('[data-request-modal]');
        const decideModal = document.querySelector('[data-decide-modal]');
        const amount = requestModal.querySelector('[data-amount]');
        const orderInput = requestModal.querySelector('[name="orderId"]');

        const setCap = () => {
            const cap = caps[orderInput.value];
            amount.max = cap || '';
            amount.placeholder = cap ? 'Up to ₦' + Number(cap).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00';
        };
        orderInput.addEventListener('change', setCap);
        setCap();

        const closeModal = (modal) => {
            if (!modal.querySelector('button.is-busy')) modal.hidden = true;
        };
        const openModal = (modal) => { modal.hidden = false; };
        document.querySelectorAll('[data-refund-open]').forEach((button) => {
            button.addEventListener('click', () => openModal(requestModal));
        });
        requestModal.querySelectorAll('[data-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(requestModal));
        });

        const decideForm = decideModal.querySelector('[data-decide-form]');
        const decideSubmit = decideModal.querySelector('[data-decide-submit]');
        document.querySelectorAll('[data-decide]').forEach((button) => {
            button.addEventListener('click', () => {
                decideForm.action = button.dataset.url;
                decideModal.querySelector('[data-decide-title]').textContent = button.dataset.title;
                decideModal.querySelector('[data-decide-copy]').textContent = button.dataset.copy;
                decideSubmit.dataset.loading = button.dataset.loading;
                decideSubmit.querySelector('[data-label]').textContent = button.dataset.submit;
                decideSubmit.classList.remove('danger', 'go');
                if (button.dataset.tone) decideSubmit.classList.add(button.dataset.tone);
                openModal(decideModal);
            });
        });
        decideModal.querySelectorAll('[data-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(decideModal));
        });

        document.querySelectorAll('form[data-busy]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                const label = button.querySelector('[data-label]');
                if (label) label.textContent = button.dataset.loading || 'Saving…';
                form.querySelectorAll('button').forEach((other) => { other.disabled = true; });
                form.closest('.refund-actions')?.querySelectorAll('button').forEach((other) => { other.disabled = true; });
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            [requestModal, decideModal].forEach((modal) => closeModal(modal));
        });
    </script>
@endsection
