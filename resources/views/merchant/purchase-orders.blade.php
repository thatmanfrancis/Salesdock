@extends('layouts.app')

@section('title', 'Purchase orders | SalesDock')

@section('content')
    @php
        $drafting = old('form') === 'po' && $errors->any();
        $lines = $drafting ? old('items', []) : [['productId' => '', 'quantity' => 1, 'unitCost' => '']];
        if ($lines === []) {
            $lines = [['productId' => '', 'quantity' => 1, 'unitCost' => '']];
        }
        $urgencies = ['LOW' => 'Low', 'MEDIUM' => 'Medium', 'HIGH' => 'High', 'CRITICAL' => 'Critical', 'OUT_OF_STOCK' => 'Out of stock'];
    @endphp
    <div class="page">
        <div class="page-tools">
            <button type="button" data-po-open>New purchase order</button>
            <x-ui.filter :action="route('inventory.orders')" :active="$filtered" label="Filter purchase orders">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Supplier">
                </div>
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'DRAFT' => 'Draft', 'SENT' => 'Sent', 'RECEIVED' => 'Received', 'CANCELLED' => 'Cancelled']" />
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Supplier</th>
                        <th>Urgency</th>
                        <th class="num">Total cost</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td><a class="ref" href="{{ route('inventory.orders.show', $order) }}">{{ strtoupper(substr($order->id, -8)) }}</a></td>
                            <td>{{ $names[$order->supplierId] ?? '—' }}</td>
                            <td><span class="badge {{ strtolower($order->urgency) }}">{{ $urgencies[$order->urgency] ?? $order->urgency }}</span></td>
                            <td class="num">₦{{ number_format((float) $order->totalCost, 2) }}</td>
                            <td><span class="badge {{ strtolower($order->status) }}">{{ ucfirst(strtolower($order->status)) }}</span></td>
                            <td>{{ $order->createdAt?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="6">No purchase orders.</td>
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

    <div class="ui-modal" data-po-modal @unless ($drafting) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet wide" method="post" action="{{ route('inventory.orders.store') }}" data-busy>
            @csrf
            <input type="hidden" name="form" value="po">
            <div class="ui-filter-head">
                <strong>New purchase order</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            @if ($suppliers->isEmpty() || $products->isEmpty())
                <p class="ask">Add a supplier and an active product before drafting a purchase order.</p>
            @else
                <x-ui.select name="supplierId" label="Supplier" :required="true" :value="$drafting ? old('supplierId', '') : ''" :options="['' => 'Choose a supplier'] + $suppliers->all()" />
                <x-ui.select name="urgency" label="Urgency" :value="$drafting ? old('urgency', 'LOW') : 'LOW'" :options="$urgencies" />
                <label>
                    <span>Notes</span>
                    <textarea name="notes" placeholder="Optional note">{{ $drafting ? old('notes') : '' }}</textarea>
                </label>
                <div class="po-lines" data-po-lines>
                    @foreach ($lines as $index => $line)
                        <div class="po-line" data-po-line>
                            <label>
                                <span>Product <span class="req">*</span></span>
                                <select name="items[{{ $index }}][productId]">
                                    <option value="">Choose a product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" @selected(($line['productId'] ?? '') === $product->id)>{{ $product->name }} ({{ $product->sku }})</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Qty <span class="req">*</span></span>
                                <input type="number" name="items[{{ $index }}][quantity]" min="1" step="1" value="{{ $line['quantity'] ?? 1 }}" required>
                            </label>
                            <label>
                                <span>Unit cost <span class="req">*</span></span>
                                <input type="number" name="items[{{ $index }}][unitCost]" min="0" step="0.01" value="{{ $line['unitCost'] ?? '' }}" placeholder="0.00" required>
                            </label>
                            <button type="button" class="quiet" data-remove-line aria-label="Remove line">✕</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="ghost line-add" data-add-line>Add item</button>
                <div class="ui-modal-actions">
                    <button type="button" data-close>Cancel</button>
                    <button type="submit" data-loading="Creating…"><span data-label>Create</span></button>
                </div>
            @endif
        </form>
    </div>
    <template id="po-line">
        <div class="po-line" data-po-line>
            <label>
                <span>Product <span class="req">*</span></span>
                <select name="items[__INDEX__][productId]">
                    <option value="">Choose a product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Qty <span class="req">*</span></span>
                <input type="number" name="items[__INDEX__][quantity]" min="1" step="1" value="1" required>
            </label>
            <label>
                <span>Unit cost <span class="req">*</span></span>
                <input type="number" name="items[__INDEX__][unitCost]" min="0" step="0.01" placeholder="0.00" required>
            </label>
            <button type="button" class="quiet" data-remove-line aria-label="Remove line">✕</button>
        </div>
    </template>
    <script>
        const modal = document.querySelector('[data-po-modal]');
        const form = modal.querySelector('form');
        const lines = modal.querySelector('[data-po-lines]');
        const template = document.querySelector('#po-line');
        const close = () => { if (!modal.querySelector('button.is-busy')) modal.hidden = true; };
        const syncRemove = () => {
            if (!lines) return;
            const rows = lines.querySelectorAll('[data-po-line]');
            rows.forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/items\[\d+\]/, 'items[' + index + ']');
                });
                row.querySelector('[data-remove-line]').disabled = rows.length < 2;
            });
        };
        document.querySelector('[data-po-open]')?.addEventListener('click', () => { modal.hidden = false; });
        modal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', close));
        document.querySelector('[data-add-line]')?.addEventListener('click', () => {
            const index = lines.querySelectorAll('[data-po-line]').length;
            lines.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
            syncRemove();
        });
        lines?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-line]');
            if (!button || lines.querySelectorAll('[data-po-line]').length < 2) return;
            button.closest('[data-po-line]').remove();
            syncRemove();
        });
        syncRemove();
        form.addEventListener('submit', () => {
            const button = form.querySelector('[type="submit"]');
            if (!button || button.disabled) return;
            button.disabled = true;
            button.classList.add('is-busy');
            const label = button.querySelector('[data-label]');
            if (label) label.textContent = button.dataset.loading || 'Creating…';
            form.querySelectorAll('button').forEach((other) => { other.disabled = true; });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') close();
        });
    </script>
@endsection
