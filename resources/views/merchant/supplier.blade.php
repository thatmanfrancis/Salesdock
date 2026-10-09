@extends('layouts.app')

@section('title', $supplier->name.' | SalesDock')

@section('content')
    @php
        $show = fn ($value) => $value ?: '—';
        $editing = old('form') === 'edit' && $errors->any();
    @endphp
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('suppliers') }}">Back</a>
            <strong>{{ $supplier->name }}</strong>
            <div class="head-actions">
                <button type="button" class="tool" data-edit-open>Edit</button>
                <button type="button" class="quiet" data-remove-open>Deactivate</button>
            </div>
        </div>

        <div class="order-grid">
            <section class="card">
                <p class="kicker band">Details</p>
                <div class="supplier-facts">
                    <div><span>Name</span><strong>{{ $supplier->name }}</strong></div>
                    <div><span>Email</span><strong>{{ $show($supplier->contactEmail) }}</strong></div>
                    <div><span>Phone</span><strong>{{ $show($supplier->contactPhone) }}</strong></div>
                    <div><span>WhatsApp</span><strong>{{ $show($supplier->contactWhatsapp) }}</strong></div>
                    <div class="wide"><span>Address</span><strong>{{ $show($supplier->address) }}</strong></div>
                    <div><span>Bank</span><strong>{{ $show($supplier->bankName) }}</strong></div>
                    <div><span>Account number</span><strong>{{ $show($supplier->bankAccount) }}</strong></div>
                    <div class="wide"><span>Notes</span><strong>{{ $show($supplier->notes) }}</strong></div>
                </div>

                <p class="kicker band">Products from this supplier</p>
                <div class="orders">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="num">In stock</th>
                                <th class="num">Threshold</th>
                                <th class="num">Reorder qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                @php $tone = (int) $product->currentStock <= 0 ? 'out' : ((int) $product->currentStock <= (int) $product->minThreshold ? 'low' : 'ok'); @endphp
                                <tr>
                                    <td><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></td>
                                    <td class="num stock {{ $tone }}">{{ $tone === 'out' ? 'Out' : $product->currentStock }}</td>
                                    <td class="num">{{ $product->minThreshold }}</td>
                                    <td class="num">{{ (int) $product->reorderQty > 0 ? $product->reorderQty : 'Auto' }}</td>
                                </tr>
                            @empty
                                <tr><td class="empty" colspan="4">No products are linked to this supplier yet. Pick this supplier on a product to link it.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="order-side">
                <section class="card restock-card">
                    <p class="kicker">Restock drafts</p>
                    <form method="post" action="{{ route('suppliers.restock', $supplier) }}" data-restock-form>
                        @csrf
                        <input type="hidden" name="restockDrafts" value="0">
                        <label class="switch">
                            <span>Draft an order when stock runs low</span>
                            <input type="checkbox" name="restockDrafts" value="1" @checked($shopRestock && $supplier->restockDrafts) @disabled(! $shopRestock) data-restock-toggle>
                        </label>
                    </form>
                    @if (! $shopRestock)
                        <p class="hint">Off for the whole shop. <a href="{{ route('settings', ['section' => 'restock']) }}">Turn it on in Settings</a> first.</p>
                    @elseif (! $supplier->contactEmail)
                        <p class="hint">Add an email for this supplier so drafts can be sent to them.</p>
                    @else
                        <p class="hint">When a product from this supplier hits its threshold, we draft a purchase order for you to review. Nothing goes to {{ $supplier->name }} until you send it.</p>
                    @endif
                    @if ($openDraft)
                        <a class="btn" href="{{ route('inventory.orders.show', $openDraft) }}">Review draft PO #{{ strtoupper(substr($openDraft->id, -8)) }}</a>
                    @endif
                </section>
                <section class="card">
                    <p class="kicker">Contact</p>
                    <div class="info-row"><span>Email</span><strong>{{ $show($supplier->contactEmail) }}</strong></div>
                    <div class="info-row"><span>Phone</span><strong>{{ $show($supplier->contactPhone) }}</strong></div>
                    <div class="info-row"><span>WhatsApp</span><strong>{{ $show($supplier->contactWhatsapp) }}</strong></div>
                </section>
                <section class="card">
                    <p class="kicker">Banking</p>
                    <div class="info-row"><span>Bank</span><strong>{{ $show($supplier->bankName) }}</strong></div>
                    <div class="info-row"><span>Account</span><strong>{{ $show($supplier->bankAccount) }}</strong></div>
                </section>
            </aside>
        </div>
    </div>

    <div class="ui-modal" data-edit-modal @unless ($editing) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('suppliers.update', $supplier) }}" data-busy>
            @csrf
            @method('PUT')
            <input type="hidden" name="form" value="edit">
            <div class="ui-filter-head">
                <strong>Edit supplier</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            @include('merchant.partials.supplier-fields', ['form' => 'edit', 'supplier' => $supplier])
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-delete-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('suppliers.destroy', $supplier) }}" data-busy>
            @csrf
            @method('DELETE')
            <div class="ui-filter-head">
                <strong>Deactivate supplier</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask">Are you sure you want to deactivate <strong>{{ $supplier->name }}</strong>? They leave the list. Past purchase orders stay on the books.</p>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" class="danger" data-loading="Deactivating…"><span data-label>Deactivate</span></button>
            </div>
        </form>
    </div>
    <script>
        const editModal = document.querySelector('[data-edit-modal]');
        const deleteModal = document.querySelector('[data-delete-modal]');
        const close = (modal) => { if (modal && !modal.querySelector('button.is-busy')) modal.hidden = true; };
        document.querySelector('[data-edit-open]')?.addEventListener('click', () => { editModal.hidden = false; });
        document.querySelector('[data-remove-open]')?.addEventListener('click', () => { deleteModal.hidden = false; });
        document.querySelector('[data-restock-toggle]')?.addEventListener('change', (event) => {
            event.target.closest('form').submit();
        });
        [editModal, deleteModal].forEach((modal) => {
            modal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', () => close(modal)));
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
            });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            close(editModal);
            close(deleteModal);
        });
    </script>
@endsection
