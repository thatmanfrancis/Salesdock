@extends('layouts.app')

@section('title', 'PO #'.$ref.' | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $urgencies = ['LOW' => 'Low', 'MEDIUM' => 'Medium', 'HIGH' => 'High', 'CRITICAL' => 'Critical', 'OUT_OF_STOCK' => 'Out of stock'];
    $draft = $order->status === 'DRAFT';
    $editing = $draft && old('form') === 'edit-po' && $errors->any();
    $lines = $editing
        ? collect(old('items', []))->values()->map(fn ($row) => [
            'productId' => (string) ($row['productId'] ?? ''),
            'quantity' => $row['quantity'] ?? 1,
            'unitCost' => $row['unitCost'] ?? 0,
        ])->all()
        : $items->map(fn ($item) => [
            'productId' => $item->productId,
            'quantity' => (int) $item->quantityOrdered,
            'unitCost' => (float) $item->unitCost,
        ])->all();
    $stockNote = function ($productId) use ($catalogue) {
        $product = $catalogue->get($productId);
        if (! $product) {
            return '';
        }
        $stock = (int) $product->currentStock;

        return ($stock <= 0 ? 'Out of stock' : $stock.' in stock').' · threshold '.(int) $product->minThreshold;
    };
    $addOptions = ['' => 'Choose a product'] + $catalogue->mapWithKeys(fn ($product) => [$product->id => $product->name.($product->sku ? ' ('.$product->sku.')' : '')])->all();
    $productData = $catalogue->map(fn ($product) => [
        'name' => $product->name,
        'note' => $stockNote($product->id),
        'quantity' => \App\Services\RestockWatcher::suggestedQuantity($product),
        'unitCost' => (float) $product->costPrice,
    ]);
@endphp

@section('content')
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('inventory.orders') }}">Back</a>
            <strong>PO #{{ $ref }}</strong>
            <span class="badge {{ strtolower($order->status) }}">{{ ucfirst(strtolower($order->status)) }}</span>
            @if ($order->isAutomatic)
                <span class="badge pending" title="Drafted automatically when stock ran low">Auto</span>
            @endif
            <div class="head-actions">
                @if (in_array($order->status, ['DRAFT', 'SENT'], true))
                    <button type="button" class="quiet" data-cancel-open>Cancel</button>
                @endif
            </div>
        </div>

        <div class="order-grid">
            <div class="order-main">
                @if ($draft)
                    <section class="card orders po-edit">
                        <p class="kicker band">Review and edit before sending</p>
                        <form method="post" action="{{ route('inventory.orders.update', $order) }}" data-po-form>
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="form" value="edit-po">
                            <input type="hidden" name="then" value="save" data-then>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="num">Qty</th>
                                        <th class="num">Unit cost (₦)</th>
                                        <th class="num">Line total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-po-rows>
                                    @foreach ($lines as $index => $line)
                                        <tr data-po-row data-product="{{ $line['productId'] }}">
                                            <td>
                                                <input type="hidden" name="items[{{ $index }}][productId]" value="{{ $line['productId'] }}">
                                                <strong>{{ $names[$line['productId']] ?? 'Removed product' }}</strong>
                                                <small class="po-note">{{ $stockNote($line['productId']) }}</small>
                                            </td>
                                            <td class="num"><input class="receive" type="number" name="items[{{ $index }}][quantity]" min="1" step="1" value="{{ $line['quantity'] }}" required data-qty></td>
                                            <td class="num"><input class="receive cost" type="number" name="items[{{ $index }}][unitCost]" min="0" step="0.01" value="{{ $line['unitCost'] }}" required data-cost></td>
                                            <td class="num" data-line-total>{{ $money((float) $line['quantity'] * (float) $line['unitCost']) }}</td>
                                            <td class="num"><button type="button" class="quiet" data-remove-row aria-label="Remove {{ $names[$line['productId']] ?? 'line' }}">✕</button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="po-edit-fields">
                                <x-ui.select name="addProduct" label="Add a product" :options="$addOptions" />
                                <x-ui.select name="urgency" label="Urgency" :value="$editing ? old('urgency', $order->urgency) : $order->urgency" :options="$urgencies" />
                                <label class="wide">
                                    <span>Note to supplier</span>
                                    <textarea name="notes" maxlength="500" placeholder="Optional, e.g. deliver before Friday">{{ $editing ? old('notes') : $order->notes }}</textarea>
                                </label>
                            </div>
                            <div class="totals">
                                <div class="grand"><span>Total</span><span data-po-total>{{ $money(collect($lines)->sum(fn ($line) => (float) $line['quantity'] * (float) $line['unitCost'])) }}</span></div>
                                <div class="ui-modal-actions po-actions">
                                    <button type="submit" class="ghost" data-then-value="save" data-loading="Saving…"><span data-label>Save draft</span></button>
                                    <button type="submit" class="ghost" data-then-value="mark" data-loading="Saving…"><span data-label>Mark as sent</span></button>
                                    <button type="submit" data-then-value="send" data-loading="Sending…" @disabled(! $canEmail)><span data-label>Send to supplier</span></button>
                                </div>
                                @unless ($canEmail)
                                    <p class="hint">
                                        {{ $supplier?->name ?? 'This supplier' }} has no email address yet.
                                        @if ($supplier)
                                            <a href="{{ route('suppliers.show', $supplier) }}">Add one</a> to send from here, or use Mark as sent if you shared it another way.
                                        @endif
                                    </p>
                                @endunless
                            </div>
                        </form>
                    </section>
                @else
                    <section class="card orders">
                        <p class="kicker band">Line items</p>
                        <form method="post" action="{{ route('inventory.orders.receive', $order) }}" data-busy>
                            @csrf
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="num">Ordered</th>
                                        <th class="num">Received</th>
                                        <th class="num">Unit cost</th>
                                        <th class="num">Line total</th>
                                        @if ($order->status === 'SENT')
                                            <th class="num">Receive qty</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($items as $item)
                                        @php $left = max(0, (int) $item->quantityOrdered - (int) $item->quantityReceived); @endphp
                                        <tr>
                                            <td>{{ $names[$item->productId] ?? '—' }}</td>
                                            <td class="num">{{ $item->quantityOrdered }}</td>
                                            <td class="num">{{ (int) $item->quantityReceived }}</td>
                                            <td class="num">{{ $money($item->unitCost) }}</td>
                                            <td class="num">{{ $money($item->lineTotal) }}</td>
                                            @if ($order->status === 'SENT')
                                                <td class="num">
                                                    <input class="receive" type="number" name="received[{{ $item->id }}]" min="0" max="{{ $left }}" step="1" value="{{ $left }}">
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr><td class="empty" colspan="{{ $order->status === 'SENT' ? 6 : 5 }}">No lines on this order.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            <div class="totals">
                                <div class="grand"><span>Total</span><span>{{ $money($order->totalCost) }}</span></div>
                                @if ($order->status === 'SENT')
                                    @if ($branches !== [])
                                        <div class="receive-branch">
                                            <x-ui.select name="branchId" label="Receive into branch" :value="$defaultBranch" :options="$branches" />
                                        </div>
                                    @endif
                                    <div class="ui-modal-actions">
                                        <button type="submit" data-loading="Receiving…"><span data-label>Receive goods</span></button>
                                    </div>
                                @endif
                            </div>
                        </form>
                    </section>
                @endif
            </div>

            <aside class="order-side">
                <section class="card">
                    <p class="kicker">Order details</p>
                    <div class="info-row"><span>Supplier</span><strong>{{ $supplier->name ?? '—' }}</strong></div>
                    <div class="info-row"><span>Urgency</span><strong>{{ $urgencies[$order->urgency] ?? $order->urgency }}</strong></div>
                    <div class="info-row"><span>Created</span><strong>{{ $order->createdAt?->format('d M Y, H:i') }}</strong></div>
                    @if ($order->isAutomatic)
                        <div class="info-row"><span>Source</span><strong>Low-stock draft</strong></div>
                    @endif
                    @if ($order->sentAt)
                        <div class="info-row"><span>Sent</span><strong>{{ $order->sentAt?->format('d M Y, H:i') }}</strong></div>
                    @endif
                    @if ($order->receivedAt)
                        <div class="info-row"><span>Received</span><strong>{{ $order->receivedAt?->format('d M Y, H:i') }}</strong></div>
                    @endif
                    @if ($order->notes && ! $draft)
                        <div class="info-row"><span>Notes</span><strong>{{ $order->notes }}</strong></div>
                    @endif
                </section>
                <section class="card">
                    <p class="kicker">Supplier contact</p>
                    <div class="info-row"><span>Email</span><strong>{{ $supplier->contactEmail ?? '—' }}</strong></div>
                    <div class="info-row"><span>Phone</span><strong>{{ $supplier->contactPhone ?? '—' }}</strong></div>
                </section>
            </aside>
        </div>
    </div>

    @if (in_array($order->status, ['DRAFT', 'SENT'], true))
        <div class="ui-modal" data-cancel-modal hidden>
            <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
            <form class="ui-modal-sheet" method="post" action="{{ route('inventory.orders.cancel', $order) }}" data-busy>
                @csrf
                <div class="ui-filter-head">
                    <strong>Cancel purchase order</strong>
                    <button type="button" class="ghost" data-close aria-label="Close">✕</button>
                </div>
                <p class="ask">Are you sure you want to cancel PO #{{ $ref }}?</p>
                <div class="ui-modal-actions">
                    <button type="button" data-close>Keep it</button>
                    <button type="submit" class="danger" data-loading="Cancelling…"><span data-label>Cancel</span></button>
                </div>
            </form>
        </div>
    @endif

    @if ($draft)
        <template data-po-template>
            <tr data-po-row data-product="">
                <td>
                    <input type="hidden" data-name="productId">
                    <strong data-row-name></strong>
                    <small class="po-note" data-row-note></small>
                </td>
                <td class="num"><input class="receive" type="number" min="1" step="1" required data-name="quantity" data-qty></td>
                <td class="num"><input class="receive cost" type="number" min="0" step="0.01" required data-name="unitCost" data-cost></td>
                <td class="num" data-line-total></td>
                <td class="num"><button type="button" class="quiet" data-remove-row aria-label="Remove line">✕</button></td>
            </tr>
        </template>
        <script type="application/json" data-po-products>@json($productData)</script>
    @endif

    <script>
        {
            const cancelModal = document.querySelector('[data-cancel-modal]');
            const closeCancel = () => { if (cancelModal && !cancelModal.querySelector('button.is-busy')) cancelModal.hidden = true; };
            document.querySelector('[data-cancel-open]')?.addEventListener('click', () => { if (cancelModal) cancelModal.hidden = false; });
            cancelModal?.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', closeCancel));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeCancel();
            });

            const busy = (form, button) => {
                if (!button || button.disabled) return;
                button.classList.add('is-busy');
                const label = button.querySelector('[data-label]');
                if (label) label.textContent = button.dataset.loading || 'Saving…';
                setTimeout(() => form.querySelectorAll('button').forEach((other) => { other.disabled = true; }), 0);
            };
            document.querySelectorAll('form[data-busy]').forEach((form) => {
                form.addEventListener('submit', (event) => busy(form, event.submitter || form.querySelector('[type="submit"]')));
            });

            const poForm = document.querySelector('[data-po-form]');
            if (poForm) {
                const rows = poForm.querySelector('[data-po-rows]');
                const template = document.querySelector('[data-po-template]');
                const products = JSON.parse(document.querySelector('[data-po-products]').textContent || '{}');
                const totalCell = poForm.querySelector('[data-po-total]');
                const thenInput = poForm.querySelector('[data-then]');
                const addInput = poForm.querySelector('[name="addProduct"]');
                const naira = (value) => '₦' + value.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                let rowIndex = rows.querySelectorAll('[data-po-row]').length;

                const recalc = () => {
                    let total = 0;
                    const all = rows.querySelectorAll('[data-po-row]');
                    all.forEach((row) => {
                        const qty = parseFloat(row.querySelector('[data-qty]').value) || 0;
                        const cost = parseFloat(row.querySelector('[data-cost]').value) || 0;
                        const line = Math.round(qty * cost * 100) / 100;
                        total += line;
                        row.querySelector('[data-line-total]').textContent = naira(line);
                        row.querySelector('[data-remove-row]').disabled = all.length < 2;
                    });
                    totalCell.textContent = naira(total);
                };

                const addRow = (productId) => {
                    const existing = rows.querySelector('[data-po-row][data-product="' + CSS.escape(productId) + '"]');
                    if (existing) {
                        existing.querySelector('[data-qty]').focus();
                        return;
                    }
                    const product = products[productId];
                    if (!product) return;
                    const row = template.content.firstElementChild.cloneNode(true);
                    row.dataset.product = productId;
                    row.querySelectorAll('[data-name]').forEach((field) => {
                        field.name = 'items[' + rowIndex + '][' + field.dataset.name + ']';
                    });
                    row.querySelector('[data-name="productId"]').value = productId;
                    row.querySelector('[data-row-name]').textContent = product.name;
                    row.querySelector('[data-row-note]').textContent = product.note;
                    row.querySelector('[data-qty]').value = product.quantity;
                    row.querySelector('[data-cost]').value = product.unitCost;
                    rowIndex += 1;
                    rows.append(row);
                    recalc();
                    row.querySelector('[data-qty]').focus();
                };

                addInput?.addEventListener('change', () => {
                    if (!addInput.value) return;
                    addRow(addInput.value);
                    addInput.value = '';
                    addInput.dispatchEvent(new Event('change', { bubbles: true }));
                });
                rows.addEventListener('input', recalc);
                rows.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-remove-row]');
                    if (!button || rows.querySelectorAll('[data-po-row]').length < 2) return;
                    button.closest('[data-po-row]').remove();
                    recalc();
                });
                poForm.querySelectorAll('[data-then-value]').forEach((button) => {
                    button.addEventListener('click', () => { thenInput.value = button.dataset.thenValue; });
                });
                poForm.addEventListener('submit', (event) => busy(poForm, event.submitter));
                recalc();
            }
        }
    </script>
@endsection
