@extends('layouts.app')

@section('title', 'Tax filings | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $reopen = old('form') === 'file' && $errors->any();
    $fileUrl = $reopen ? route('tax-filings.file', old('filing')) : '';
@endphp

@section('content')
    <div class="page">
        <p class="tax-note">Each month is built from the sales ledger after it closes. VAT is due on the 21st. SalesDock does not collect the payment.</p>

        <div class="page-tools">
            <x-ui.filter :action="route('tax-filings')" :active="$filtered" label="Filter filings">
                <x-ui.select name="year" label="Year" :value="$year" :options="$years" />
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Period</th>
                        <th class="num">Gross</th>
                        <th class="num">VAT</th>
                        <th class="num">Net</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filings as $filing)
                        @php
                            $label = \Illuminate\Support\Carbon::parse($filing->period.'-01')->format('F Y');
                            $due = $filing->dueDate?->timezone('Africa/Lagos')->format('d M Y') ?: '—';
                            $open = $filing->status !== 'FILED';
                        @endphp
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="num">{{ $money($filing->grossSales) }}</td>
                            <td class="num vat">{{ $money($filing->totalVat) }}</td>
                            <td class="num profit">{{ $money($filing->netSales) }}</td>
                            <td>{{ $due }}</td>
                            <td><span class="badge {{ strtolower($filing->status) }}">{{ ucfirst(strtolower($filing->status)) }}</span></td>
                            <td>
                                <div class="refund-actions">
                                    <a href="{{ route('tax-filings.show', $filing) }}">Audit</a>
                                    <a href="{{ route('tax-filings.pdf', $filing) }}" target="_blank" rel="noopener">PDF</a>
                                    @if ($open)
                                        <button type="button" class="process" data-pay data-period="{{ $label }}" data-vat="{{ $money($filing->totalVat) }}" data-due="{{ $due }}" data-pdf="{{ route('tax-filings.pdf', $filing) }}">Pay VAT</button>
                                        <button type="button" class="approve" data-file data-url="{{ route('tax-filings.file', $filing) }}" data-id="{{ $filing->id }}" data-period="{{ $label }}">Submit filing</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="7">No tax filings yet.</td>
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

    <div class="ui-modal" data-pay-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <div class="ui-modal-sheet">
            <div class="ui-filter-head">
                <strong data-pay-title>Pay VAT</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask">VAT due <strong data-pay-vat></strong> by <span data-pay-due></span>. Pay it to NRS on the Self Service portal. SalesDock does not take this payment.</p>
            <ol class="tax-steps">
                <li>Download the VAT return.</li>
                <li>File it on the NRS Self Service portal and pay there.</li>
                <li>Come back and submit the filing with the reference number.</li>
            </ol>
            <div class="ui-modal-actions">
                <button type="button" data-close>Close</button>
                <a class="go" data-pay-pdf href="#" target="_blank" rel="noopener">Download PDF</a>
                <a class="go" href="https://selfservice.nrs.gov.ng/" target="_blank" rel="noopener">Open NRS Self Service</a>
            </div>
        </div>
    </div>

    <div class="ui-modal" data-file-modal @unless ($reopen) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ $fileUrl }}" enctype="multipart/form-data" data-busy>
            @csrf
            <input type="hidden" name="form" value="file">
            <input type="hidden" name="filing" value="{{ old('filing') }}" data-file-id>
            <div class="ui-filter-head">
                <strong data-file-title>{{ $reopen ? 'Submit filing' : 'Submit filing' }}</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask">This confirms the return was submitted to FIRS and the VAT was paid. The row locks after this.</p>
            <label>
                <span>FIRS reference <span class="req">*</span></span>
                <input name="submissionRef" value="{{ $reopen ? old('submissionRef') : '' }}" placeholder="FIRS/VAT/2026/123456" required maxlength="120">
            </label>
            @error('submissionRef')
                <p class="ask">{{ $message }}</p>
            @enderror
            <div class="csv-file" data-proof>
                <input type="file" name="proof" accept=".pdf,.png,.jpg,.jpeg,image/png,image/jpeg,application/pdf" data-proof-input>
                <button type="button" data-proof-open>Choose receipt</button>
                <span data-proof-name>{{ $reopen && old('proof') ? old('proof') : 'Optional. PDF or image, up to 5 MB.' }}</span>
            </div>
            @error('proof')
                <p class="ask">{{ $message }}</p>
            @enderror
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Submitting…"><span data-label>Submit filing</span></button>
            </div>
        </form>
    </div>

    <script>
        const payModal = document.querySelector('[data-pay-modal]');
        const fileModal = document.querySelector('[data-file-modal]');
        const fileForm = fileModal.querySelector('form');
        const closeModal = (modal) => {
            if (!modal.querySelector('button.is-busy')) modal.hidden = true;
        };
        payModal.querySelectorAll('[data-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(payModal));
        });
        fileModal.querySelectorAll('[data-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(fileModal));
        });
        document.querySelectorAll('[data-pay]').forEach((button) => {
            button.addEventListener('click', () => {
                payModal.querySelector('[data-pay-title]').textContent = 'Pay VAT · ' + button.dataset.period;
                payModal.querySelector('[data-pay-vat]').textContent = button.dataset.vat;
                payModal.querySelector('[data-pay-due]').textContent = button.dataset.due;
                payModal.querySelector('[data-pay-pdf]').href = button.dataset.pdf;
                payModal.hidden = false;
            });
        });
        document.querySelectorAll('[data-file]').forEach((button) => {
            button.addEventListener('click', () => {
                fileForm.action = button.dataset.url;
                fileForm.querySelector('[data-file-id]').value = button.dataset.id;
                fileModal.querySelector('[data-file-title]').textContent = 'Submit filing · ' + button.dataset.period;
                fileModal.hidden = false;
            });
        });
        document.querySelectorAll('[data-proof]').forEach((box) => {
            const input = box.querySelector('[data-proof-input]');
            const name = box.querySelector('[data-proof-name]');
            box.querySelector('[data-proof-open]').addEventListener('click', () => input.click());
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                if (!file) {
                    name.textContent = 'Optional. PDF or image, up to 5 MB.';
                    return;
                }
                if (!/\.(pdf|png|jpe?g)$/i.test(file.name)) {
                    input.value = '';
                    name.textContent = 'Choose a PDF or image.';
                    return;
                }
                name.textContent = file.name;
            });
        });
        document.querySelectorAll('form[data-busy]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                const label = button.querySelector('[data-label]');
                if (label) label.textContent = button.dataset.loading || 'Submitting…';
                form.querySelectorAll('button').forEach((other) => { other.disabled = true; });
            });
        });
    </script>
@endsection
