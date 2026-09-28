@extends('layouts.app')

@section('title', 'Starter Pack | SalesDock')

@section('content')
    <div class="page">
        <div class="page-tools">
            <a class="tool" href="{{ route('products') }}">← Back to products</a>
            <span class="plan-pill @if ($remaining <= 0) full @endif">
                {{ $used }} / {{ $limit }} products · {{ $remaining }} slot{{ $remaining === 1 ? '' : 's' }} remaining
            </span>
        </div>

        <section class="card">
            <p class="kicker">Starter pack — {{ $businessType ?? 'General' }}</p>
            <p class="muted">
                These catalogue items match your business type. Tick the ones you sell, set your price, and import them all in one go.
                @if ($remaining < $items->count())
                    Your plan allows <strong>{{ $remaining }}</strong> more product{{ $remaining === 1 ? '' : 's' }} — only the first {{ $remaining }} selected will be imported.
                @endif
            </p>

            @if ($items->isEmpty())
                <p class="muted" style="padding:1rem 0">
                    No catalogue items found for your business type yet.
                    <a href="{{ route('products', ['panel' => 'catalogue']) }}">Search the catalogue manually →</a>
                </p>
            @else
                <form method="post" action="{{ route('products.starter.import') }}" id="starter-form">
                    @csrf

                    {{-- Group by category --}}
                    @php $grouped = $items->groupBy('category'); @endphp

                    @foreach ($grouped as $cat => $group)
                        <div class="starter-group">
                            <div class="starter-group-head">
                                <strong>{{ $cat ?: 'General' }}</strong>
                                <button type="button" class="starter-select-all" data-group="{{ $loop->index }}">Select all</button>
                            </div>
                            @foreach ($group as $i => $item)
                                @php
                                    $alreadyIn = in_array(mb_strtolower($item->name), $existingNames, true);
                                    $idx = $items->search(fn ($r) => $r->id === $item->id);
                                @endphp
                                <div class="starter-row @if ($alreadyIn) starter-row--done @endif" data-group="{{ $loop->parent->index }}">
                                    <label class="starter-check">
                                        <input type="checkbox" name="items[{{ $idx }}][id]" value="{{ $item->id }}"
                                            data-idx="{{ $idx }}"
                                            @disabled($alreadyIn)
                                            @if ($alreadyIn) title="Already in your shop" @endif>
                                        <span class="starter-name">
                                            {{ $item->name }}
                                            @if ($item->brand) <em>{{ $item->brand }}</em> @endif
                                            @if ($alreadyIn) <span class="pill">In shop</span> @endif
                                        </span>
                                    </label>
                                    <label class="starter-price">
                                        <span>₦ Price</span>
                                        <input type="number" name="items[{{ $idx }}][price]"
                                            step="0.01" min="0.01" placeholder="0.00"
                                            data-idx="{{ $idx }}"
                                            @disabled($alreadyIn)>
                                    </label>
                                    <label class="starter-stock">
                                        <span>Stock</span>
                                        <input type="number" name="items[{{ $idx }}][stock]"
                                            min="0" placeholder="0"
                                            data-idx="{{ $idx }}"
                                            @disabled($alreadyIn)>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    <div class="starter-footer">
                        <span id="starter-count">0 selected</span>
                        @if ($remaining > 0)
                            <button type="submit" id="starter-submit" disabled data-loading="Importing…">
                                <span data-label>Import selected</span>
                            </button>
                        @else
                            <span class="muted">Plan limit reached. <a href="{{ route('billing') }}">Upgrade</a> to add more.</span>
                        @endif
                    </div>
                </form>
            @endif
        </section>
    </div>

    <script>
        const form    = document.getElementById('starter-form');
        const countEl = document.getElementById('starter-count');
        const submit  = document.getElementById('starter-submit');
        const limit   = {{ $remaining }};

        function syncCount() {
            const checked = form ? form.querySelectorAll('input[type="checkbox"]:checked').length : 0;
            if (countEl) countEl.textContent = checked + ' selected';
            if (submit) submit.disabled = checked === 0;

            // Disable unchecked boxes once limit reached, re-enable if unchecked
            if (form) {
                form.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach((cb) => {
                    if (!cb.checked && checked >= limit) {
                        cb.disabled = true;
                        cb.dataset.limitDisabled = '1';
                    } else if (cb.dataset.limitDisabled === '1') {
                        cb.disabled = false;
                        delete cb.dataset.limitDisabled;
                    }
                });
            }
        }

        form?.addEventListener('change', (e) => {
            if (e.target.type === 'checkbox') syncCount();
        });

        // Select-all per group
        document.querySelectorAll('.starter-select-all').forEach((btn) => {
            btn.addEventListener('click', () => {
                const group = btn.dataset.group;
                const boxes = form.querySelectorAll('[data-group="' + group + '"] input[type="checkbox"]:not([disabled])');
                const allChecked = Array.from(boxes).every((b) => b.checked);
                boxes.forEach((b) => { b.checked = !allChecked; });
                syncCount();
            });
        });

        // Require price when checkbox is checked
        form?.addEventListener('change', (e) => {
            if (e.target.type !== 'checkbox') return;
            const idx      = e.target.dataset.idx;
            const priceIn  = form.querySelector('[name="items[' + idx + '][price]"]');
            if (priceIn) priceIn.required = e.target.checked;
        });

        // Busy state
        form?.addEventListener('submit', () => {
            if (!submit || submit.disabled) return;
            submit.disabled = true;
            submit.classList.add('is-busy');
            const label = submit.querySelector('[data-label]');
            if (label) label.textContent = submit.dataset.loading || 'Importing…';
        });

        syncCount();
    </script>
@endsection
