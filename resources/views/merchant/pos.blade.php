@extends('layouts.app')

@section('title', 'POS | SalesDock')

@section('content')
    @php
        $categories = $products->pluck('category')->filter()->unique()->sort()->values();
    @endphp

    {{-- Offline banner --}}
    <div id="pos-offline-banner" class="pos-offline-banner" hidden>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55M5 12.55a10.94 10.94 0 0 1 5.17-2.39M10.71 5.05A16 16 0 0 1 22.56 9M1.42 9a15.91 15.91 0 0 1 4.7-2.88M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
        <span id="pos-offline-text">You're offline — sales are saved and will sync automatically when you're back online.</span>
        <span id="pos-sync-count" hidden class="pos-sync-count">0 pending</span>
        <button type="button" id="pos-sync-now" hidden>Sync now</button>
    </div>
    <div class="pos" data-vat="{{ $vatRate }}" data-inclusive="{{ $vatInclusive ? '1' : '0' }}">
        <div class="pos-catalog">
            <div class="pos-search">
                <div class="pos-search-box">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
                    <input id="pos-query" type="search" placeholder="Search or scan barcode…" autocomplete="off">
                </div>
                <button type="button" class="pos-icon" id="pos-filter" aria-label="Filter by category" aria-expanded="false">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                </button>
                <button type="button" class="pos-icon" id="pos-help" aria-label="Keyboard shortcuts">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><circle cx="12" cy="17" r="0.5" fill="currentColor"/></svg>
                </button>
            </div>
            @if ($categories->isNotEmpty())
                <div class="pos-cats" id="pos-cats" hidden>
                    <button type="button" class="on" data-category="">All</button>
                    @foreach ($categories as $category)
                        <button type="button" data-category="{{ $category }}">{{ $category }}</button>
                    @endforeach
                </div>
            @endif
            <div class="pos-grid @if ($products->isEmpty()) is-empty @endif" id="pos-grid">
                @forelse ($products as $product)
                    @php $available = (int) $product->currentStock - (int) $product->reservedQty; @endphp
                    <button
                        type="button"
                        class="pos-product"
                        data-id="{{ $product->id }}"
                        data-name="{{ $product->name }}"
                        data-sku="{{ $product->sku }}"
                        data-price="{{ $product->price }}"
                        data-stock="{{ $available }}"
                        data-category="{{ $product->category }}"
                        @disabled($available <= 0)
                    >
                        <span class="pos-qty" hidden>0</span>
                        <span class="pos-thumb">
                            @if ($product->imageUrl)
                                <img src="{{ $product->imageUrl }}" alt="">
                            @else
                                <span>No img</span>
                            @endif
                        </span>
                        <strong>{{ $product->name }}</strong>
                        <em>{{ $product->sku }}</em>
                        <b>₦{{ number_format($product->price, 2) }}</b>
                        @if ($available <= 0)
                            <small>Out of stock</small>
                        @endif
                    </button>
                @empty
                    <p class="pos-blank">No products yet.</p>
                @endforelse
                {{-- Static filter empty state — shown/hidden by JS only --}}
                <p class="pos-blank pos-none" id="pos-empty-msg" hidden style="grid-column:1/-1"></p>
            </div>
        </div>

        <div class="pos-ticket">
            <div class="pos-tabs">
                <button type="button" class="on" data-tab="cart">Cart <i id="pos-cart-count" hidden>0</i></button>
                <button type="button" data-tab="holds">Holds @if ($holds->isNotEmpty())<i>{{ $holds->count() }}</i>@endif</button>
                <button type="button" data-tab="void">Void</button>
            </div>

            <form class="pos-pane" method="post" action="{{ route('pos.store') }}" id="pos-sale" data-pane="cart">
                @csrf
                <input type="hidden" name="method" value="CASH" id="pos-method">
                <input type="hidden" name="discount" value="0">
                <input type="hidden" name="hold_label" value="" id="pos-hold">
                <input type="hidden" name="resume_hold" value="{{ $resumeHold }}">
                <div id="pos-lines"></div>
                <div class="pos-cart" id="pos-cart">
                    <p class="pos-blank">Cart is empty<br><span>Add products from the left</span></p>
                </div>
                <div class="pos-totals">
                    <p><span>Sub Total</span><strong id="pos-sub">₦0.00</strong></p>
                    <p><span>Tax {{ rtrim(rtrim(number_format($vatRate, 2), '0'), '.') }}% @if ($vatInclusive)(Included)@endif</span><strong id="pos-tax">₦0.00</strong></p>
                    <p class="due"><span>Total Amount</span><strong id="pos-due">₦0.00</strong></p>
                </div>
                <div class="pos-pay">
                    <button type="button" class="on" data-method="CASH">Cash</button>
                    <button type="button" data-method="CARD">Card</button>
                    <button type="button" data-method="TRANSFER">Transfer</button>
                </div>
                <div class="pos-actions">
                    <div class="pos-hold">
                        <button type="button" id="pos-hold-open" disabled>Hold</button>
                        <div class="pos-hold-menu" id="pos-hold-menu" hidden>
                            @foreach (['Back in 5 mins', 'Back in 10 mins', 'Back in 15 mins', 'Back in 30 mins', 'Customer browsing'] as $label)
                                <button type="submit" data-hold="{{ $label }}">{{ $label }}</button>
                            @endforeach
                            <button type="button" id="pos-hold-custom">Custom note…</button>
                            <label id="pos-hold-note" hidden>
                                <input name="hold_note_draft" placeholder="e.g. Guy in blue shirt…" maxlength="120">
                                <button type="submit" id="pos-hold-save">Hold</button>
                            </label>
                        </div>
                    </div>
                    <button type="button" id="pos-place" disabled>Complete Sale</button>
                </div>
            </form>

            <div class="pos-pane" data-pane="holds" hidden>
                @forelse ($holds as $hold)
                    <article>
                        <div>
                            <strong>{{ $hold['reference'] }}</strong>
                            <span>{{ $hold['count'] }} {{ $hold['count'] === 1 ? 'item' : 'items' }} · {{ $hold['total'] }}</span>
                            @if ($hold['expires'])
                                <span>Expires {{ $hold['expires'] }}</span>
                            @endif
                        </div>
                        <form method="post" action="{{ route('pos.holds.restore', $hold['id']) }}">
                            @csrf
                            <button type="submit">Restore</button>
                        </form>
                        <form method="post" action="{{ route('pos.holds.drop', $hold['id']) }}">
                            @csrf
                            <button type="submit" class="drop" aria-label="Drop hold">×</button>
                        </form>
                    </article>
                @empty
                    <p class="pos-blank">No active holds</p>
                @endforelse
            </div>

            <form class="pos-pane pos-void" method="post" action="{{ route('pos.void') }}" data-pane="void" hidden>
                @csrf
                <strong>Void Everything</strong>
                <p>Clearing this cart does not need approval. Cancelling parked sales in the shop needs a supervisor, manager, or owner on this screen.</p>
                @if ($holds->isNotEmpty())
                    <label>Reason <input name="reason" maxlength="255" required></label>
                    @if ($needsPin)
                        <label>Supervisor PIN <input name="pin" inputmode="numeric" autocomplete="off" required></label>
                    @endif
                @endif
                <button type="submit" id="pos-void" @disabled($holds->isEmpty())>Void All</button>
            </form>
        </div>
    </div>

    {{-- Cash payment modal --}}
    <div class="pos-overlay" id="pos-cash-modal" hidden>
        <div class="pos-modal">
            <div class="pos-modal-head">
                <strong>Cash Payment</strong>
                <button type="button" class="pos-modal-close" id="pos-cash-close" aria-label="Close">✕</button>
            </div>
            <div class="pos-modal-body">
                <div class="pos-modal-row">
                    <span>Total due</span>
                    <strong id="cash-total">₦0.00</strong>
                </div>
                <div class="pos-modal-field">
                    <label for="cash-tendered">Cash received</label>
                    <input id="cash-tendered" type="number" step="0.01" min="0" placeholder="0.00" inputmode="decimal" autocomplete="off">
                </div>
                <div class="pos-modal-row pos-modal-change">
                    <span>Change</span>
                    <strong id="cash-change">—</strong>
                </div>
            </div>
            <div class="pos-modal-foot">
                <button type="button" id="pos-cash-cancel">Cancel</button>
                <button type="button" id="pos-cash-confirm" disabled>Complete Sale</button>
            </div>
        </div>
    </div>

    {{-- Success modal --}}
    <div class="pos-overlay" id="pos-success-modal" hidden>
        <div class="pos-modal pos-modal-success">
            <div class="pos-success-icon">
                <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-5"/></svg>
            </div>
            <strong id="success-title">Sale Complete</strong>
            <p id="success-ref"></p>
            <p id="success-total"></p>
            <p class="pos-receipt-prompt">Print receipt?</p>
            <div class="pos-modal-foot">
                <button type="button" id="pos-no-receipt">No, done</button>
                <a id="pos-print-receipt" href="#" target="_blank">Yes, print</a>
            </div>
        </div>
    </div>

    {{-- Shortcuts modal --}}
    <div class="pos-overlay" id="pos-shortcuts-modal" hidden>
        <div class="pos-modal">
            <div class="pos-modal-head">
                <strong>Keyboard shortcuts</strong>
                <button type="button" class="pos-modal-close" id="pos-shortcuts-close" aria-label="Close">✕</button>
            </div>
            <div class="pos-shortcuts">
                <div class="pos-shortcut-row"><kbd>/</kbd><span>Focus search</span></div>
                <div class="pos-shortcut-row"><kbd>Esc</kbd><span>Clear search / close modal</span></div>
                <div class="pos-shortcut-row"><kbd>1</kbd><span>Select Cash payment</span></div>
                <div class="pos-shortcut-row"><kbd>2</kbd><span>Select Card payment</span></div>
                <div class="pos-shortcut-row"><kbd>3</kbd><span>Select Transfer payment</span></div>
                <div class="pos-shortcut-row"><kbd>Enter</kbd><span>Complete sale (when cart has items)</span></div>
                <div class="pos-shortcut-row"><kbd>?</kbd><span>Show this help</span></div>
            </div>
        </div>
    </div>

    <script>
        const root = document.querySelector('.pos');
        const rate = Number(root.dataset.vat) / 100;
        const inclusive = root.dataset.inclusive === '1';
        const money = (amount) => '₦' + amount.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const cart = new Map();
        @foreach ($resume as $line)
            cart.set(@json($line['id']), {{ (int) $line['qty'] }});
        @endforeach
        const grid = document.getElementById('pos-grid');
        const cartBox = document.getElementById('pos-cart');
        const lines = document.getElementById('pos-lines');
        const query = document.getElementById('pos-query');
        let category = '';
        let currentDue = 0;

        const naira = (product) => Number(product.dataset.price) || 0;

        function paint() {
            lines.replaceChildren();
            cartBox.replaceChildren();
            let gross = 0;
            let count = 0;
            grid.querySelectorAll('.pos-product').forEach((button) => {
                const qty = cart.get(button.dataset.id) || 0;
                const badge = button.querySelector('.pos-qty');
                badge.hidden = qty < 1;
                badge.textContent = String(qty);
                button.classList.toggle('in', qty > 0);
            });
            if (cart.size === 0) {
                cartBox.innerHTML = '<p class="pos-blank">Cart is empty<br><span>Add products from the left</span></p>';
            }
            cart.forEach((qty, id) => {
                const product = grid.querySelector('[data-id="' + CSS.escape(id) + '"]');
                if (!product) return;
                gross += naira(product) * qty;
                count += qty;
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'qty[' + id + ']';
                input.value = String(qty);
                lines.append(input);
                const row = document.createElement('div');
                row.className = 'pos-line';
                row.innerHTML = '<div><strong></strong><b></b></div><div class="pos-step"><button type="button" data-step="-1">−</button><span></span><button type="button" data-step="1">+</button><button type="button" data-step="0">×</button></div>';
                row.querySelector('strong').textContent = product.dataset.name;
                row.querySelector('b').textContent = money(naira(product) * qty);
                row.querySelector('.pos-step span').textContent = String(qty);
                row.querySelectorAll('[data-step]').forEach((button) => {
                    button.addEventListener('click', () => change(id, Number(button.dataset.step)));
                });
                cartBox.append(row);
            });
            const tax = inclusive ? gross - (gross / (1 + (rate || 0))) : gross * rate;
            const due = inclusive ? gross : gross + tax;
            currentDue = due;
            document.getElementById('pos-sub').textContent = money(inclusive ? gross - tax : gross);
            document.getElementById('pos-tax').textContent = money(tax);
            document.getElementById('pos-due').textContent = money(due);
            const badge = document.getElementById('pos-cart-count');
            badge.hidden = count < 1;
            badge.textContent = count > 9 ? '9+' : String(count);
            document.getElementById('pos-place').disabled = cart.size === 0;
            document.getElementById('pos-hold-open').disabled = cart.size === 0;
            const voidButton = document.getElementById('pos-void');
            if (voidButton) voidButton.disabled = cart.size === 0 && {{ $holds->isEmpty() ? 'true' : 'false' }};
            filterProducts();
        }

        function change(id, step) {
            const product = grid.querySelector('[data-id="' + CSS.escape(id) + '"]');
            const stock = Number(product?.dataset.stock || 0);
            const next = step === 0 ? 0 : (cart.get(id) || 0) + step;
            if (next <= 0) cart.delete(id);
            else cart.set(id, Math.min(next, Math.max(stock, 1)));
            paint();
        }

        grid?.addEventListener('click', (event) => {
            const product = event.target.closest('.pos-product');
            if (!product || product.disabled) return;
            change(product.dataset.id, 1);
        });

        function filterProducts() {
            const term = query.value.trim().toLowerCase();
            let shown = 0;
            grid.querySelectorAll('.pos-product').forEach((btn) => {
                const hay = (btn.dataset.name + ' ' + btn.dataset.sku).toLowerCase();
                const ok = (!term || hay.includes(term)) && (!category || btn.dataset.category === category);
                btn.hidden = !ok;
                if (ok) shown += 1;
            });
            const hasFilter = term !== '' || category !== '';
            const empty = document.getElementById('pos-empty-msg');
            if (empty) {
                empty.hidden = !hasFilter || shown > 0;
                if (!empty.hidden) {
                    empty.textContent = term ? 'No results for "' + query.value.trim() + '"' : 'No products in this category';
                }
            }
        }

        query?.addEventListener('input', filterProducts);
        document.getElementById('pos-filter')?.addEventListener('click', () => {
            const cats = document.getElementById('pos-cats');
            if (!cats) return;
            cats.hidden = !cats.hidden;
            document.getElementById('pos-filter').setAttribute('aria-expanded', cats.hidden ? 'false' : 'true');
            document.getElementById('pos-filter').classList.toggle('on', !cats.hidden || category !== '');
        });
        document.getElementById('pos-cats')?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-category]');
            if (!button) return;
            category = button.dataset.category;
            document.querySelectorAll('#pos-cats button').forEach((item) => item.classList.toggle('on', item === button));
            document.getElementById('pos-filter').classList.toggle('on', category !== '');
            filterProducts();
        });
        document.querySelectorAll('.pos-pay button').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('pos-method').value = button.dataset.method;
                document.querySelectorAll('.pos-pay button').forEach((item) => item.classList.toggle('on', item === button));
            });
        });
        document.querySelectorAll('.pos-tabs button').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.pos-tabs button').forEach((item) => item.classList.toggle('on', item === button));
                document.querySelectorAll('.pos-pane').forEach((pane) => {
                    pane.hidden = pane.dataset.pane !== button.dataset.tab;
                });
            });
        });
        const holdMenu = document.getElementById('pos-hold-menu');
        document.getElementById('pos-hold-open')?.addEventListener('click', () => {
            holdMenu.hidden = !holdMenu.hidden;
        });
        holdMenu?.addEventListener('click', (event) => {
            const choice = event.target.closest('[data-hold]');
            if (choice) document.getElementById('pos-hold').value = choice.dataset.hold;
        });
        document.getElementById('pos-hold-custom')?.addEventListener('click', () => {
            document.getElementById('pos-hold-note').hidden = false;
        });
        document.getElementById('pos-hold-save')?.addEventListener('click', (event) => {
            const note = holdMenu.querySelector('[name="hold_note_draft"]').value.trim();
            if (!note) { event.preventDefault(); return; }
            document.getElementById('pos-hold').value = note;
        });

        // ── Checkout flow ────────────────────────────────────────────────────
        const form = document.getElementById('pos-sale');
        const cashModal = document.getElementById('pos-cash-modal');
        const successModal = document.getElementById('pos-success-modal');
        const shortcutsModal = document.getElementById('pos-shortcuts-modal');

        function closeOverlays() {
            cashModal.hidden = true;
            successModal.hidden = true;
            shortcutsModal.hidden = true;
        }

        async function submitSale() {
            const btn = document.getElementById('pos-place');
            btn.disabled = true;
            btn.textContent = 'Processing…';
            const fd = new FormData(form);
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    alert(err.message || 'Something went wrong. Please try again.');
                    btn.disabled = false;
                    btn.textContent = 'Complete Sale';
                    return;
                }
                const data = await res.json();
                cart.clear();
                paint();
                if (data.offline) {
                    // Queued offline — show a different success state
                    document.getElementById('success-title').textContent = 'Sale saved offline';
                    document.getElementById('success-ref').textContent = 'Ref: ' + data.ref + ' (pending sync)';
                    document.getElementById('success-total').textContent = money(currentDue);
                    document.getElementById('pos-print-receipt').closest('.pos-modal-foot').querySelector('a').hidden = true;
                    document.querySelector('.pos-receipt-prompt').textContent = 'Sale queued — will sync when online.';
                } else {
                    document.getElementById('success-title').textContent = 'Sale Complete';
                    document.getElementById('success-ref').textContent = 'Ref: ' + data.ref;
                    document.getElementById('success-total').textContent = data.total;
                    document.getElementById('pos-print-receipt').href = data.orderUrl;
                    document.getElementById('pos-print-receipt').hidden = false;
                    document.querySelector('.pos-receipt-prompt').textContent = 'Print receipt?';
                }
                successModal.hidden = false;
            } catch (e) {
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Complete Sale';
            }
        }

        document.getElementById('pos-place').addEventListener('click', () => {
            const method = document.getElementById('pos-method').value;
            if (method === 'CASH') {
                document.getElementById('cash-total').textContent = money(currentDue);
                document.getElementById('cash-tendered').value = '';
                document.getElementById('cash-change').textContent = '—';
                document.getElementById('pos-cash-confirm').disabled = true;
                cashModal.hidden = false;
                setTimeout(() => document.getElementById('cash-tendered').focus(), 60);
            } else {
                document.getElementById('pos-hold').value = '';
                submitSale();
            }
        });

        // Cash modal logic
        document.getElementById('cash-tendered').addEventListener('input', () => {
            const paid = parseFloat(document.getElementById('cash-tendered').value) || 0;
            const change = paid - currentDue;
            const changeEl = document.getElementById('cash-change');
            const confirmBtn = document.getElementById('pos-cash-confirm');
            if (paid >= currentDue) {
                changeEl.textContent = money(change);
                changeEl.style.color = '#16a34a';
                confirmBtn.disabled = false;
            } else {
                changeEl.textContent = paid > 0 ? '–' + money(currentDue - paid) + ' short' : '—';
                changeEl.style.color = paid > 0 ? '#ef4444' : '';
                confirmBtn.disabled = true;
            }
        });

        document.getElementById('pos-cash-confirm').addEventListener('click', () => {
            cashModal.hidden = true;
            document.getElementById('pos-hold').value = '';
            submitSale();
        });

        document.getElementById('pos-cash-cancel').addEventListener('click', () => { cashModal.hidden = true; });
        document.getElementById('pos-cash-close').addEventListener('click', () => { cashModal.hidden = true; });

        // Success modal actions
        document.getElementById('pos-no-receipt').addEventListener('click', () => { successModal.hidden = true; });

        // Overlay backdrop click
        [cashModal, successModal, shortcutsModal].forEach((overlay) => {
            overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.hidden = true; });
        });

        // Shortcuts modal
        document.getElementById('pos-help').addEventListener('click', () => { shortcutsModal.hidden = false; });
        document.getElementById('pos-shortcuts-close').addEventListener('click', () => { shortcutsModal.hidden = true; });

        // Hold submit (normal form submit for holds)
        form.addEventListener('submit', (event) => {
            const submitter = event.submitter;
            // Only allow holds through as normal form submits
            if (submitter && submitter.dataset.hold !== undefined) {
                document.getElementById('pos-hold').value = submitter.dataset.hold || '';
                return; // let it submit normally
            }
            if (submitter && submitter.id === 'pos-hold-save') return;
            // Prevent default for everything else (handled via JS above)
            event.preventDefault();
        });

        // ── Keyboard shortcuts ───────────────────────────────────────────────
        document.addEventListener('keydown', (event) => {
            const tag = document.activeElement?.tagName;
            const typing = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
            const anyOpen = !cashModal.hidden || !successModal.hidden || !shortcutsModal.hidden;

            if (event.key === 'Escape') {
                if (anyOpen) { closeOverlays(); return; }
                if (!typing) { query.value = ''; query.focus(); filterProducts(); }
                return;
            }

            if (anyOpen) return;

            if (event.key === '/' && !typing) {
                event.preventDefault();
                query.focus();
                query.select();
                return;
            }

            if (event.key === '?' && !typing) {
                shortcutsModal.hidden = false;
                return;
            }

            if (!typing) {
                if (event.key === '1') {
                    document.querySelector('[data-method="CASH"]')?.click();
                } else if (event.key === '2') {
                    document.querySelector('[data-method="CARD"]')?.click();
                } else if (event.key === '3') {
                    document.querySelector('[data-method="TRANSFER"]')?.click();
                } else if (event.key === 'Enter') {
                    const btn = document.getElementById('pos-place');
                    if (!btn.disabled) btn.click();
                }
            }
        });

        paint();
    </script>

    {{-- Service Worker registration + offline/online banner logic --}}
    <script>
        (function () {
            const banner    = document.getElementById('pos-offline-banner');
            const bannerTxt = document.getElementById('pos-offline-text');
            const countEl   = document.getElementById('pos-sync-count');
            const syncBtn   = document.getElementById('pos-sync-now');

            function setOffline(offline) {
                banner.hidden = !offline && (parseInt(countEl.textContent) || 0) === 0;
                if (offline) {
                    bannerTxt.textContent = 'You\'re offline — sales are saved locally and will sync when you\'re back online.';
                    banner.hidden = false;
                }
            }

            function showCount(count) {
                if (count > 0) {
                    countEl.hidden  = false;
                    syncBtn.hidden  = false;
                    countEl.textContent = count + ' pending sale' + (count === 1 ? '' : 's');
                    banner.hidden   = false;
                    if (navigator.onLine) {
                        bannerTxt.textContent = 'Back online — ' + count + ' pending sale' + (count === 1 ? '' : 's') + ' ready to sync.';
                    }
                } else {
                    countEl.hidden = true;
                    syncBtn.hidden = true;
                    if (navigator.onLine) banner.hidden = true;
                }
            }

            function askCount() {
                navigator.serviceWorker?.controller?.postMessage({ type: 'GET_QUEUE_COUNT' });
            }

            function sync() {
                navigator.serviceWorker?.controller?.postMessage({ type: 'SYNC_NOW' });
                syncBtn.disabled = true;
                syncBtn.textContent = 'Syncing…';
            }

            syncBtn?.addEventListener('click', sync);
            window.addEventListener('offline', () => setOffline(true));
            window.addEventListener('online',  () => {
                setOffline(false);
                askCount();
                // Small delay to let SW controller attach
                setTimeout(sync, 800);
            });

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js');

                navigator.serviceWorker.addEventListener('message', ({ data }) => {
                    if (!data) return;
                    if (data.type === 'SALE_QUEUED') {
                        askCount();
                    }
                    if (data.type === 'QUEUE_COUNT') {
                        showCount(data.count);
                    }
                    if (data.type === 'SYNC_DONE') {
                        syncBtn.disabled = false;
                        syncBtn.textContent = 'Sync now';
                        if (data.synced > 0) {
                            bannerTxt.textContent = data.synced + ' sale' + (data.synced === 1 ? '' : 's') + ' synced successfully.';
                            setTimeout(() => { banner.hidden = true; }, 4000);
                        }
                        askCount();
                    }
                });

                navigator.serviceWorker.ready.then(askCount);
            }

            // Show banner immediately if offline on page load
            if (!navigator.onLine) setOffline(true);
        })();
    </script>
@endsection
