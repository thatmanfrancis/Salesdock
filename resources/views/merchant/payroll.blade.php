@extends('layouts.app')

@section('title', 'Payroll | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $month = function ($period) {
        try {
            return \Illuminate\Support\Carbon::createFromFormat('!Y-m', (string) $period)->format('M Y');
        } catch (\Throwable) {
            return $period;
        }
    };
@endphp

@section('content')
    <div class="page">
        <div class="page-tools">
            <button type="button" data-payroll-open>Process payroll</button>
            <x-ui.filter :action="route('payroll')" :active="$filtered" label="Filter payroll">
                <div class="ui-field">
                    <span>Month</span>
                    <input type="month" name="period" value="{{ $period }}">
                </div>
                <x-ui.select name="staff" label="Staff" :value="$staffId" :options="['' => 'All staff'] + $people->all()" />
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'paid' => 'Paid', 'pending' => 'Pending']" />
            </x-ui.filter>
        </div>

        @if ($summary['payslips'] > 0)
            <div class="ledger-line">
                <article>
                    <span>Total payroll</span>
                    <strong title="{{ $money($summary['total']) }}">{{ $money($summary['total']) }}</strong>
                </article>
                <article>
                    <span>Paid out</span>
                    <strong class="gain" title="{{ $money($summary['paid']) }}">{{ $money($summary['paid']) }}</strong>
                </article>
                <article>
                    <span>Pending</span>
                    <strong>{{ $summary['pending'] }}</strong>
                </article>
                <article>
                    <span>Payslips</span>
                    <strong class="rate">{{ $summary['payslips'] }}</strong>
                </article>
            </div>
        @endif

        <section class="card orders payroll">
            <table>
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Period</th>
                        <th class="num">Base salary</th>
                        <th class="num">Bonus</th>
                        <th class="num">Deductions</th>
                        <th class="num">Net pay</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                {{ $row->user?->name ?: '—' }}
                                @if ($row->user?->role?->name)
                                    <span class="sku">{{ $row->user->role->name }}</span>
                                @endif
                            </td>
                            <td>{{ $month($row->period) }}</td>
                            <td class="num">{{ $money($row->baseSalary) }}</td>
                            <td class="num profit">{{ $money($row->bonus) }}</td>
                            <td class="num spend">{{ $money($row->deductions) }}</td>
                            <td class="num net">{{ $money($row->netPay) }}</td>
                            <td><span class="badge {{ $row->isPaid ? 'active' : 'pending' }}">{{ $row->isPaid ? 'Paid' : 'Pending' }}</span></td>
                            <td>
                                <div class="more">
                                    <button type="button" data-more aria-label="Actions for {{ $row->user?->name ?: 'payslip' }}" aria-expanded="false">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>
                                    <div class="more-menu" hidden>
                                        <button type="button" data-view data-name="{{ $row->user?->name ?: '—' }}" data-role="{{ $row->user?->role?->name ?: '—' }}" data-period="{{ $month($row->period) }}" data-base="{{ $money($row->baseSalary) }}" data-bonus="{{ $money($row->bonus) }}" data-deductions="{{ $money($row->deductions) }}" data-net="{{ $money($row->netPay) }}" data-notes="{{ $row->notes }}" data-paid="{{ $row->isPaid ? 'Paid' : 'Pending' }}" data-when="{{ $row->createdAt?->timezone('Africa/Lagos')->format('d M Y') }}">View</button>
                                        <button type="button" data-edit data-url="{{ route('payroll.update', $row) }}" data-id="{{ $row->id }}" data-user="{{ $row->userId }}" data-period="{{ $row->period }}" data-salary="{{ $row->baseSalary }}" data-bonus="{{ $row->bonus }}" data-deductions="{{ $row->deductions }}" data-notes="{{ $row->notes }}">Edit</button>
                                        <a href="{{ route('payroll.print', $row) }}" target="_blank" rel="noopener">Print</a>
                                        @unless ($row->isPaid)
                                            <button type="button" data-pay data-url="{{ route('payroll.pay', $row) }}" data-name="{{ $row->user?->name ?: 'this person' }}" data-period="{{ $month($row->period) }}">Mark paid</button>
                                        @endunless
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="8">No payroll records.</td>
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

    @php
        $creating = old('form') === 'create' && $errors->any();
        $editing = old('form') === 'edit' && $errors->any();
        $kept = $creating || $editing;
    @endphp
    <div class="ui-modal" data-create-modal @unless ($kept) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ $editing ? route('payroll.update', old('payroll')) : route('payroll.store') }}" data-busy>
            @csrf
            <input type="hidden" name="_method" value="{{ $editing ? 'PUT' : 'POST' }}" data-method>
            <input type="hidden" name="form" value="{{ $editing ? 'edit' : 'create' }}" data-form>
            <input type="hidden" name="payroll" value="{{ $editing ? old('payroll') : '' }}" data-payroll-id>
            <div class="ui-filter-head">
                <strong data-payroll-title>{{ $editing ? 'Edit payslip' : 'Process payroll' }}</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <div class="ui-modal-grid">
                <x-ui.select name="userId" label="Staff" :required="true" :value="$kept ? old('userId', '') : ''" :options="$people->all()" />
                <label>
                    <span>Month <span class="req">*</span></span>
                    <input type="month" name="period" value="{{ $kept ? old('period') : '' }}" required>
                </label>
                <label>
                    <span>Base salary (₦) <span class="req">*</span></span>
                    <input type="number" name="baseSalary" min="0" step="0.01" value="{{ $kept ? old('baseSalary') : '' }}" placeholder="0.00" required data-money>
                </label>
                <label>
                    <span>Bonus (₦)</span>
                    <input type="number" name="bonus" min="0" step="0.01" value="{{ $kept ? old('bonus', '0') : '0' }}" placeholder="0.00" data-money>
                </label>
                <label>
                    <span>Deductions (₦)</span>
                    <input type="number" name="deductions" min="0" step="0.01" value="{{ $kept ? old('deductions', '0') : '0' }}" placeholder="0.00" data-money>
                </label>
                <div class="net-preview wide" data-net-preview @if (! $kept || old('baseSalary') === null || old('baseSalary') === '') hidden @endif>
                    <span>Net pay</span>
                    <strong data-net-figure>₦0.00</strong>
                </div>
                <label class="wide">
                    <span>Notes</span>
                    <textarea name="notes" maxlength="2000" placeholder="Optional notes">{{ $kept ? old('notes') : '' }}</textarea>
                </label>
            </div>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="{{ $editing ? 'Saving…' : 'Processing…' }}" data-save><span data-label>{{ $editing ? 'Save' : 'Process' }}</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-view-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <div class="ui-modal-sheet">
            <div class="ui-filter-head">
                <strong>Payslip</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <div class="payslip">
                <p class="who"><strong data-view-name></strong><span data-view-meta></span></p>
                <div class="supplier-facts">
                    <div><span>Base salary</span><strong data-view-base></strong></div>
                    <div><span>Bonus</span><strong class="profit" data-view-bonus></strong></div>
                    <div><span>Deductions</span><strong class="spend" data-view-deductions></strong></div>
                    <div><span>Net pay</span><strong class="gain" data-view-net></strong></div>
                </div>
                <p class="note" data-view-notes hidden></p>
                <p class="when"><span data-view-paid></span><span data-view-when></span></p>
            </div>
        </div>
    </div>

    <div class="ui-modal" data-pay-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('payroll.store') }}" data-busy data-pay-form>
            @csrf
            <div class="ui-filter-head">
                <strong>Mark paid</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask">Mark <strong data-pay-name>this payslip</strong> as paid?</p>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Marking…"><span data-label>Mark paid</span></button>
            </div>
        </form>
    </div>

    <script>
        const closeMenus = () => {
            document.querySelectorAll('.more-menu').forEach((menu) => { menu.hidden = true; });
            document.querySelectorAll('[data-more]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        };
        const closeModal = (modal) => { if (!modal.querySelector('button.is-busy')) modal.hidden = true; };
        document.querySelectorAll('[data-more]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                const menu = button.parentElement.querySelector('.more-menu');
                const show = menu.hidden;
                closeMenus();
                menu.hidden = !show;
                button.setAttribute('aria-expanded', show ? 'true' : 'false');
            });
        });
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.more')) closeMenus();
        });
        const createModal = document.querySelector('[data-create-modal]');
        const viewModal = document.querySelector('[data-view-modal]');
        const payModal = document.querySelector('[data-pay-modal]');
        const form = createModal.querySelector('form');
        const setStaff = (id) => {
            const field = form.querySelector('[data-select]');
            const input = field.querySelector('[data-select-value]');
            const label = field.querySelector('[data-select-label]');
            input.value = id || '';
            let chosen = 'Choose';
            field.querySelectorAll('[role="option"]').forEach((option) => {
                const on = option.dataset.value === input.value;
                option.setAttribute('aria-selected', on ? 'true' : 'false');
                if (on) chosen = option.textContent.trim();
            });
            label.textContent = chosen;
        };
        const openCreate = () => {
            form.action = @json(route('payroll.store'));
            form.querySelector('[data-method]').value = 'POST';
            form.querySelector('[data-form]').value = 'create';
            form.querySelector('[data-payroll-id]').value = '';
            createModal.querySelector('[data-payroll-title]').textContent = 'Process payroll';
            setStaff('');
            form.querySelector('[name="period"]').value = '';
            form.querySelector('[name="baseSalary"]').value = '';
            form.querySelector('[name="bonus"]').value = '0';
            form.querySelector('[name="deductions"]').value = '0';
            form.querySelector('[name="notes"]').value = '';
            const save = form.querySelector('[data-save]');
            save.dataset.loading = 'Processing…';
            save.querySelector('[data-label]').textContent = 'Process';
            paint();
            createModal.hidden = false;
        };
        document.querySelector('[data-payroll-open]').addEventListener('click', openCreate);
        document.querySelectorAll('[data-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                closeMenus();
                form.action = button.dataset.url;
                form.querySelector('[data-method]').value = 'PUT';
                form.querySelector('[data-form]').value = 'edit';
                form.querySelector('[data-payroll-id]').value = button.dataset.id;
                createModal.querySelector('[data-payroll-title]').textContent = 'Edit payslip';
                setStaff(button.dataset.user);
                form.querySelector('[name="period"]').value = button.dataset.period || '';
                form.querySelector('[name="baseSalary"]').value = button.dataset.salary || '';
                form.querySelector('[name="bonus"]').value = button.dataset.bonus || '0';
                form.querySelector('[name="deductions"]').value = button.dataset.deductions || '0';
                form.querySelector('[name="notes"]').value = button.dataset.notes || '';
                const save = form.querySelector('[data-save]');
                save.dataset.loading = 'Saving…';
                save.querySelector('[data-label]').textContent = 'Save';
                paint();
                createModal.hidden = false;
            });
        });
        document.querySelectorAll('[data-view]').forEach((button) => {
            button.addEventListener('click', () => {
                closeMenus();
                viewModal.querySelector('[data-view-name]').textContent = button.dataset.name;
                viewModal.querySelector('[data-view-meta]').textContent = button.dataset.role + ' · ' + button.dataset.period;
                viewModal.querySelector('[data-view-base]').textContent = button.dataset.base;
                viewModal.querySelector('[data-view-bonus]').textContent = '+ ' + button.dataset.bonus;
                viewModal.querySelector('[data-view-deductions]').textContent = '− ' + button.dataset.deductions;
                viewModal.querySelector('[data-view-net]').textContent = button.dataset.net;
                const notes = viewModal.querySelector('[data-view-notes]');
                notes.hidden = !button.dataset.notes;
                notes.textContent = button.dataset.notes || '';
                viewModal.querySelector('[data-view-paid]').textContent = button.dataset.paid;
                viewModal.querySelector('[data-view-when]').textContent = 'Processed ' + button.dataset.when;
                viewModal.hidden = false;
            });
        });
        document.querySelectorAll('[data-pay]').forEach((button) => {
            button.addEventListener('click', () => {
                closeMenus();
                payModal.querySelector('[data-pay-form]').action = button.dataset.url;
                payModal.querySelector('[data-pay-name]').textContent = button.dataset.name + '’s ' + button.dataset.period + ' payslip';
                payModal.hidden = false;
            });
        });
        [createModal, viewModal, payModal].forEach((modal) => {
            modal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', () => closeModal(modal)));
        });
        const preview = form.querySelector('[data-net-preview]');
        const figure = form.querySelector('[data-net-figure]');
        const paint = () => {
            const base = form.querySelector('[name="baseSalary"]').value;
            const net = (Number(base) || 0) + (Number(form.querySelector('[name="bonus"]').value) || 0) - (Number(form.querySelector('[name="deductions"]').value) || 0);
            preview.hidden = base === '';
            figure.textContent = '₦' + net.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };
        form.querySelectorAll('[data-money]').forEach((input) => input.addEventListener('input', paint));
        paint();
        document.querySelectorAll('form[data-busy]').forEach((box) => {
            box.addEventListener('submit', () => {
                const button = box.querySelector('[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                const label = button.querySelector('[data-label]');
                if (label) label.textContent = button.dataset.loading || 'Saving…';
            });
        });
    </script>
@endsection
