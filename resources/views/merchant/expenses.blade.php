@extends('layouts.app')

@section('title', 'Expenses | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $signed = fn ($amount) => ((float) $amount < 0 ? '−' : '').$money(abs((float) $amount));
    $creating = old('form') === 'create' && $errors->any();
    $editing = old('form') === 'edit' && $errors->any();
    $open = $creating || $editing;
    $bars = $pnl['gross'] > 0 ? [
        ['label' => 'Revenue', 'amount' => $pnl['gross'], 'width' => 100, 'color' => '#22c55e'],
        ['label' => 'Cost of goods', 'amount' => $pnl['cogs'], 'width' => min(100, ($pnl['cogs'] / $pnl['gross']) * 100), 'color' => '#f87171'],
        ['label' => 'Operating expenses', 'amount' => $pnl['operating'], 'width' => min(100, ($pnl['operating'] / $pnl['gross']) * 100), 'color' => '#fb923c'],
        ['label' => 'Net profit', 'amount' => max(0, $pnl['net']), 'width' => max(0, min(100, ($pnl['net'] / $pnl['gross']) * 100)), 'color' => '#10b981'],
    ] : [];
@endphp

@section('content')
    <div class="page">
        <div class="ledger-line wide">
            <article>
                <span>Gross revenue</span>
                <strong title="{{ $money($pnl['gross']) }}">{{ $money($pnl['gross']) }}</strong>
            </article>
            <article>
                <span>Cost of goods</span>
                <strong class="cost" title="{{ $money($pnl['cogs']) }}">{{ $money($pnl['cogs']) }}</strong>
            </article>
            <article>
                <span>Gross profit</span>
                <strong class="gain" title="{{ $signed($pnl['profit']) }}">{{ $signed($pnl['profit']) }}</strong>
                <em>{{ $pnl['profitMargin'] }}% margin</em>
            </article>
            <article>
                <span>Operating expenses</span>
                <strong class="spend" title="{{ $money($pnl['operating']) }}">{{ $money($pnl['operating']) }}</strong>
            </article>
            <article>
                <span>Net profit</span>
                <strong class="{{ $pnl['net'] < 0 ? 'loss' : 'gain' }}" title="{{ $signed($pnl['net']) }}">{{ $signed($pnl['net']) }}</strong>
                <em>{{ $pnl['netMargin'] }}% net margin</em>
            </article>
        </div>

        @if ($bars !== [] || $pills->isNotEmpty())
            <section class="pnl">
                @foreach ($bars as $bar)
                    <div class="pnl-row">
                        <span>{{ $bar['label'] }}</span>
                        <div class="pnl-track"><i style="width: {{ number_format($bar['width'], 1, '.', '') }}%; background: {{ $bar['color'] }}"></i></div>
                        <strong>{{ $money($bar['amount']) }}</strong>
                        <em>{{ number_format($bar['width'], 1) }}%</em>
                    </div>
                @endforeach
                @if ($pills->isNotEmpty())
                    <div class="cat-pills">
                        @foreach ($pills as $pill)
                            <span>{{ $labels[$pill->category] ?? $pill->category }} {{ $money($pill->total) }}</span>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        <div class="page-tools">
            <button type="button" data-expense-open>Log expense</button>
            <x-ui.filter :action="route('expenses')" :active="$filtered" label="Filter expenses">
                <x-ui.select name="category" label="Category" :value="$category" :options="$categories" />
                <div class="ui-filter-dates">
                    <x-ui.date name="from" label="From" :value="$from" />
                    <x-ui.date name="to" label="To" :value="$to" />
                </div>
            </x-ui.filter>
        </div>

        <section class="card orders expenses">
            <p class="kicker band">Total {{ $money($totalAmount) }}</p>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th class="num">Amount</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        @php
                            $when = $expense->date?->timezone('Africa/Lagos')->format('Y-m-d');
                        @endphp
                        <tr>
                            <td>{{ $expense->date?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</td>
                            <td>{{ $expense->title }}</td>
                            <td><span class="pill">{{ $labels[$expense->category] ?? $expense->category }}</span></td>
                            <td class="num spend">{{ $money($expense->amount) }}</td>
                            <td class="clip" title="{{ $expense->notes }}">{{ $expense->notes ?: '—' }}</td>
                            <td>
                                <div class="more">
                                    <button type="button" data-more aria-label="Actions for {{ $expense->title }}" aria-expanded="false">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>
                                    <div class="more-menu" hidden>
                                        <button type="button" data-edit data-url="{{ route('expenses.update', $expense) }}" data-id="{{ $expense->id }}" data-title="{{ $expense->title }}" data-category="{{ $expense->category }}" data-amount="{{ $expense->amount }}" data-date="{{ $when }}" data-notes="{{ $expense->notes }}">Edit</button>
                                        <button type="button" class="danger" data-remove data-url="{{ route('expenses.destroy', $expense) }}" data-title="{{ $expense->title }}">Delete</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="6">No expenses logged.</td>
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

    <div class="ui-modal" data-expense-modal @unless ($open) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ $editing ? route('expenses.update', old('expense')) : route('expenses.store') }}" data-busy data-expense-form data-today="{{ $today }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif
            <input type="hidden" name="form" value="{{ $editing ? 'edit' : 'create' }}" data-form-kind>
            <input type="hidden" name="expense" value="{{ old('expense') }}" data-expense-id>
            <div class="ui-filter-head">
                <strong data-expense-heading>{{ $editing ? 'Edit expense' : 'Log expense' }}</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <label>
                <span>Title <span class="req">*</span></span>
                <input name="title" value="{{ $open ? old('title') : '' }}" placeholder="Monthly rent" required maxlength="255" data-field="title">
            </label>
            <x-ui.select name="category" label="Category" :required="true" :value="$open ? old('category', 'OTHER') : 'OTHER'" :options="$labels" />
            <x-ui.date name="date" label="Date" :required="true" :value="$open ? old('date', $today) : $today" />
            <label>
                <span>Amount (₦) <span class="req">*</span></span>
                <input type="number" name="amount" step="0.01" min="0.01" value="{{ $open ? old('amount') : '' }}" placeholder="0.00" required data-field="amount">
            </label>
            <label>
                <span>Notes</span>
                <textarea name="notes" placeholder="Optional details" maxlength="2000" data-field="notes">{{ $open ? old('notes') : '' }}</textarea>
            </label>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="{{ $editing ? 'Saving…' : 'Creating…' }}"><span data-label>{{ $editing ? 'Save' : 'Log expense' }}</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-delete-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" data-busy data-delete-form>
            @csrf
            @method('DELETE')
            <div class="ui-filter-head">
                <strong>Delete expense</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask">Are you sure you want to delete <strong data-delete-name>this expense</strong>? This cannot be undone.</p>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" class="danger" data-loading="Deleting…"><span data-label>Delete</span></button>
            </div>
        </form>
    </div>

    <script>
        const modal = document.querySelector('[data-expense-modal]');
        const form = modal.querySelector('[data-expense-form]');
        const deleteModal = document.querySelector('[data-delete-modal]');
        const closeMenus = () => {
            document.querySelectorAll('.more-menu').forEach((menu) => { menu.hidden = true; });
            document.querySelectorAll('[data-more]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        };
        const closeModal = (box) => {
            if (!box.querySelector('button.is-busy')) box.hidden = true;
        };
        const setField = (name, value) => {
            const input = form.querySelector(`[name="${name}"]`);
            if (!input) return;
            input.value = value ?? '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };
        const setMethod = (verb) => {
            let method = form.querySelector('[name="_method"]');
            if (verb === 'POST') {
                method?.remove();
                return;
            }
            if (!method) {
                method = document.createElement('input');
                method.type = 'hidden';
                method.name = '_method';
                form.append(method);
            }
            method.value = verb;
        };
        const openExpense = (mode, data) => {
            closeMenus();
            form.action = data.url;
            form.querySelector('[data-form-kind]').value = mode;
            form.querySelector('[data-expense-id]').value = data.id || '';
            setMethod(mode === 'edit' ? 'PUT' : 'POST');
            setField('title', data.title || '');
            setField('category', data.category || 'OTHER');
            setField('date', data.date || form.dataset.today);
            setField('amount', data.amount || '');
            setField('notes', data.notes || '');
            modal.querySelector('[data-expense-heading]').textContent = mode === 'edit' ? 'Edit expense' : 'Log expense';
            const submit = form.querySelector('[type="submit"]');
            submit.dataset.loading = mode === 'edit' ? 'Saving…' : 'Creating…';
            submit.querySelector('[data-label]').textContent = mode === 'edit' ? 'Save' : 'Log expense';
            modal.hidden = false;
        };
        document.querySelector('[data-expense-open]').addEventListener('click', () => {
            openExpense('create', { url: @json(route('expenses.store')) });
        });
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
        document.querySelectorAll('[data-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                openExpense('edit', {
                    url: button.dataset.url,
                    id: button.dataset.id,
                    title: button.dataset.title,
                    category: button.dataset.category,
                    amount: button.dataset.amount,
                    date: button.dataset.date,
                    notes: button.dataset.notes,
                });
            });
        });
        document.querySelectorAll('[data-remove]').forEach((button) => {
            button.addEventListener('click', () => {
                closeMenus();
                deleteModal.querySelector('[data-delete-form]').action = button.dataset.url;
                deleteModal.querySelector('[data-delete-name]').textContent = button.dataset.title;
                deleteModal.hidden = false;
            });
        });
        modal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', () => closeModal(modal)));
        deleteModal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', () => closeModal(deleteModal)));
        document.querySelectorAll('form[data-busy]').forEach((box) => {
            box.addEventListener('submit', () => {
                const button = box.querySelector('[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                const label = button.querySelector('[data-label]');
                if (label) label.textContent = button.dataset.loading || 'Saving…';
                box.querySelectorAll('button').forEach((other) => { other.disabled = true; });
            });
        });
    </script>
@endsection
