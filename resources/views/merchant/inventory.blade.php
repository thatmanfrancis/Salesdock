@extends('layouts.app')

@section('title', 'Inventory | SalesDock')

@section('content')
    @php
        $adjusting = old('form') === 'adjust' && $errors->any();
        $columns = $seesCosts ? 8 : 7;
        $branchOptions = $branches->mapWithKeys(fn ($row) => [$row->id => $row->name.($row->isMain ? ' (main)' : '')])->all();
    @endphp
    <div class="page">
        <div class="page-tools">
            <a class="tool" href="{{ route('inventory.orders') }}">Purchase orders</a>
            <x-ui.filter :action="route('inventory')" :active="$filtered" label="Filter inventory">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Name, SKU, or barcode">
                </div>
                <x-ui.select name="stock" label="Stock" :value="$stock" :options="['' => 'All', 'low' => 'Low stock', 'out' => 'Out of stock']" />
                @if ($branchOptions !== [])
                    <x-ui.select name="branch" label="Branch" :value="$branch" :options="['' => 'All branches'] + $branchOptions" />
                @endif
            </x-ui.filter>
        </div>

        @if ($branch !== '')
            <p class="note branch-scope">Showing stock at <strong>{{ $branchOptions[$branch] ?? 'this branch' }}</strong>. Hover a count to see the total across all branches.</p>
        @endif

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th class="num">In stock</th>
                        <th class="num">Reserved</th>
                        <th class="num">Threshold</th>
                        @if ($seesCosts)
                            <th class="num">Cost</th>
                        @endif
                        <th></th>
                    </tr>
                </thead>
                <tbody data-live-region="inventory">
                    @forelse ($products as $product)
                        @php
                            $level = $branchQty !== null ? (int) ($branchQty[$product->id] ?? 0) : (int) $product->currentStock;
                            $tone = $level <= 0 ? 'out' : ($level <= (int) $product->minThreshold ? 'low' : 'ok');
                        @endphp
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td><span class="ref">{{ $product->sku }}</span></td>
                            <td>{{ $product->category ?: '—' }}</td>
                            <td class="num stock {{ $tone }}" @if ($branchQty !== null) title="{{ $product->currentStock }} across all branches" @endif>{{ $tone === 'out' ? 'Out' : $level }}</td>
                            <td class="num">{{ $product->reservedQty }}</td>
                            <td class="num">{{ $product->minThreshold }}</td>
                            @if ($seesCosts)
                                <td class="num">₦{{ number_format((float) $product->costPrice, 2) }}</td>
                            @endif
                            <td>
                                <button type="button" class="adjust" data-adjust data-id="{{ $product->id }}" data-name="{{ $product->name }}">Adjust</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="{{ $columns }}">No products found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <nav class="pager" aria-label="Pages" data-live-region="inventory-pager">
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

    <div class="ui-modal" data-adjust-modal @unless ($adjusting) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('inventory.adjust') }}" data-busy>
            @csrf
            <input type="hidden" name="form" value="adjust">
            <input type="hidden" name="productId" value="{{ $adjusting ? old('productId') : '' }}" data-product>
            <div class="ui-filter-head">
                <strong data-adjust-title>Adjust stock</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            @if ($branchOptions !== [])
                <x-ui.select name="branchId" label="Branch" :required="true" :value="$adjusting ? old('branchId', array_key_first($branchOptions)) : ($branch !== '' ? $branch : array_key_first($branchOptions))" :options="$branchOptions" />
            @endif
            <x-ui.select name="type" label="Movement" :required="true" :value="$adjusting ? old('type', 'ADJUSTMENT_IN') : 'ADJUSTMENT_IN'" :options="['ADJUSTMENT_IN' => 'Stock in', 'ADJUSTMENT_OUT' => 'Stock out']" />
            <label>
                <span>Quantity <span class="req">*</span></span>
                <input type="number" name="quantity" min="1" step="1" value="{{ $adjusting ? old('quantity') : '' }}" placeholder="1" required>
            </label>
            <label>
                <span>Notes</span>
                <input name="notes" value="{{ $adjusting ? old('notes') : '' }}" placeholder="Optional note" maxlength="255">
            </label>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Updating…"><span data-label>Update</span></button>
            </div>
        </form>
    </div>
    <script>
        const modal = document.querySelector('[data-adjust-modal]');
        const form = modal.querySelector('form');
        const close = () => { if (!modal.querySelector('button.is-busy')) modal.hidden = true; };
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-adjust]');
            if (!button) return;
            form.querySelector('[data-product]').value = button.dataset.id;
            modal.querySelector('[data-adjust-title]').textContent = 'Adjust ' + button.dataset.name;
            form.querySelector('[name="quantity"]').value = '';
            form.querySelector('[name="notes"]').value = '';
            modal.hidden = false;
        });
        modal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', close));
        form.addEventListener('submit', () => {
            const button = form.querySelector('[type="submit"]');
            if (!button || button.disabled) return;
            button.disabled = true;
            button.classList.add('is-busy');
            const label = button.querySelector('[data-label]');
            if (label) label.textContent = button.dataset.loading || 'Updating…';
            form.querySelectorAll('button').forEach((other) => { other.disabled = true; });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') close();
        });
    </script>
    @include('partials.live-data')
@endsection
