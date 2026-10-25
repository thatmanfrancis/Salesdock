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
            <p class="kicker">Starter pack</p>
            <h1 style="margin:0 0 0.35rem;font-size:1.25rem;">Fill your shop from ready-made items</h1>
            <p class="muted">
                This is a short-cut list from the SalesDock catalogue — common products shops like yours already sell.
                You do not invent them from scratch: tick what you sell, type your price, and import them into your inventory.
            </p>

            <div class="starter-howto">
                <h2>How it works</h2>
                <ol>
                    <li>Pick a category below (or keep the suggested ones for {{ $businessType ?? 'your shop' }}).</li>
                    <li>Tick each product you sell. Use <strong>Select all</strong> on a group if you want everything in that group.</li>
                    <li>Type your selling price (and opening stock if you already have some).</li>
                    <li>Click <strong>Import selected</strong>. Those items appear on Products and at the POS immediately.</li>
                </ol>
                <p class="muted">
                    Already in your shop? Those rows are greyed out and labelled “In shop”.
                    @if ($remaining < $items->count())
                        Your plan has <strong>{{ $remaining }}</strong> free slot{{ $remaining === 1 ? '' : 's' }} left — only that many can be imported this time.
                    @endif
                </p>
            </div>

            <form method="get" action="{{ route('products.starter') }}" id="starter-category-form" class="branch-pick" style="margin-bottom:1rem">
                <x-ui.select name="category" label="Show category" :value="$pickedCategory" :options="$categoryOptions" :searchable="true" />
            </form>

            @if ($items->isEmpty())
                <p class="muted" style="padding:1rem 0">
                    No catalogue items in this view yet.
                    @if ($pickedCategory === '' && ($businessType ?? null))
                        Try another category above, or
                    @endif
                    <a href="{{ route('products', ['panel' => 'catalogue']) }}">search the full catalogue →</a>
                </p>
            @else
                <form method="post" action="{{ route('products.starter.import') }}" id="starter-form">
                    @csrf
                    @if ($branches !== [])
                        <div class="branch-pick">
                            <x-ui.select name="branchId" label="Stock goes to" :value="array_key_first($branches)" :options="$branches" />
                        </div>
                    @endif

                    <div class="starter-footer" style="border-top:0;padding-top:0;margin-bottom:0.75rem">
                        <button type="button" class="starter-select-all" id="starter-select-all-page">Select all on this page</button>
                        <span class="muted">Or tick only the ones you sell</span>
                    </div>

                    @php $grouped = $items->groupBy('category'); @endphp

                    @foreach ($grouped as $cat => $group)
                        <div class="starter-group">
                            <div class="starter-group-head">
                                <strong>{{ $cat ?: 'General' }}</strong>
                                <button type="button" class="starter-select-all" data-group="{{ $loop->index }}">Select all</button>
                            </div>
                            @foreach ($group as $item)
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
                                        <span>₦ Your price</span>
                                        <input type="number" name="items[{{ $idx }}][price]"
                                            step="0.01" min="0.01" placeholder="0.00"
                                            data-idx="{{ $idx }}"
                                            @disabled($alreadyIn)>
                                    </label>
                                    <label class="starter-stock">
                                        <span>Opening stock</span>
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
                        <span id="starter-count">0 selected · ready to import</span>
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
        const categoryForm = document.getElementById('starter-category-form');
        categoryForm?.addEventListener('change', (event) => {
            if (event.target.name === 'category') categoryForm.requestSubmit();
        });

        const form    = document.getElementById('starter-form');
        const countEl = document.getElementById('starter-count');
        const submit  = document.getElementById('starter-submit');
        const limit   = {{ $remaining }};

        function syncCount() {
            const checked = form ? form.querySelectorAll('input[type="checkbox"]:checked').length : 0;
            if (countEl) countEl.textContent = checked + ' selected · ready to import';
            if (submit) submit.disabled = checked === 0;

            if (form) {
                form.querySelectorAll('input[type="checkbox"]:not([title])').forEach((cb) => {
                    if (cb.dataset.already) return;
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

        function toggleBoxes(boxes, force) {
            const allChecked = Array.from(boxes).every((b) => b.checked);
            const next = force === undefined ? !allChecked : force;
            boxes.forEach((b) => { if (!b.disabled || b.dataset.limitDisabled === '1') { b.disabled = false; delete b.dataset.limitDisabled; b.checked = next; } });
            boxes.forEach((b) => {
                const idx = b.dataset.idx;
                const priceIn = form.querySelector('[name="items[' + idx + '][price]"]');
                if (priceIn && !priceIn.disabled) priceIn.required = b.checked;
            });
            syncCount();
        }

        form?.addEventListener('change', (e) => {
            if (e.target.type !== 'checkbox') return;
            const idx = e.target.dataset.idx;
            const priceIn = form.querySelector('[name="items[' + idx + '][price]"]');
            if (priceIn) priceIn.required = e.target.checked;
            syncCount();
        });

        document.querySelectorAll('.starter-select-all[data-group]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const boxes = form.querySelectorAll('[data-group="' + btn.dataset.group + '"] input[type="checkbox"]:not([disabled]), [data-group="' + btn.dataset.group + '"] input[type="checkbox"][data-limit-disabled]');
                toggleBoxes(boxes);
            });
        });

        document.getElementById('starter-select-all-page')?.addEventListener('click', () => {
            const boxes = form.querySelectorAll('input[type="checkbox"]:not([title])');
            toggleBoxes(boxes);
        });

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
