@extends('layouts.app')

@section('title', 'Promotions | SalesDock')

@section('content')
    @php $editing = old('form') === 'edit' && $errors->any(); @endphp
    <div class="page">
        <div class="page-tools">
            <button type="button" data-promo-open>New promotion</button>
            <x-ui.filter :action="route('promotions')" :active="$filtered" label="Filter promotions">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Promotion or product">
                </div>
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']" />
            </x-ui.filter>
        </div>

        <section class="card orders promotions">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="num">Value</th>
                        <th>Product</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($promotions as $promo)
                        @php
                            $amount = (float) $promo->discountValue;
                            $pretty = rtrim(rtrim(number_format($amount, 2), '0'), '.');
                            $value = $promo->discountType === 'PERCENTAGE' ? $pretty.'%' : '₦'.number_format($amount, 2);
                        @endphp
                        <tr>
                            <td>{{ $promo->name }}</td>
                            <td>{{ $promo->discountType === 'PERCENTAGE' ? 'Percentage' : 'Fixed amount' }}</td>
                            <td class="num">{{ $value }}</td>
                            <td>
                                @if ($names[$promo->productId] ?? null)
                                    <span class="promo-pill">{{ $names[$promo->productId] }}</span>
                                @else
                                    Store-wide
                                @endif
                            </td>
                            <td>{{ $promo->startDatetime?->format('d M Y') }} → {{ $promo->endDatetime?->format('d M Y') }}</td>
                            <td>
                                <form class="promo-toggle" method="post" action="{{ route('promotions.toggle', $promo) }}" data-busy>
                                    @csrf
                                    <button type="submit" class="status @if ($promo->isActive) on @endif" data-loading="Updating…"><span data-label>{{ $promo->isActive ? 'Active' : 'Inactive' }}</span></button>
                                </form>
                            </td>
                            <td>
                                <div class="more">
                                    <button type="button" data-more aria-label="Actions for {{ $promo->name }}" aria-expanded="false">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>
                                    <div class="more-menu" hidden>
                                        <button type="button" data-edit data-url="{{ route('promotions.update', $promo) }}" data-id="{{ $promo->id }}" data-name="{{ $promo->name }}" data-type="{{ $promo->discountType }}" data-value="{{ $pretty }}" data-product="{{ $promo->productId }}" data-product-name="{{ $names[$promo->productId] ?? '' }}" data-start="{{ $promo->startDatetime?->format('Y-m-d') }}" data-end="{{ $promo->endDatetime?->format('Y-m-d') }}">Edit</button>
                                        <button type="button" class="danger" data-remove data-url="{{ route('promotions.destroy', $promo) }}" data-name="{{ $promo->name }}">Delete</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="7">No promotions yet.</td>
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

    <div class="ui-modal" data-create-modal @unless (old('form') === 'create' && $errors->any()) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('promotions.store') }}" data-busy data-promo-form>
            @csrf
            <input type="hidden" name="form" value="create">
            <div class="ui-filter-head">
                <strong>New promotion</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            @include('merchant.partials.promo-fields', ['form' => 'create'])
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Creating…"><span data-label>Create</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-edit-modal @unless ($editing) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ $editing && old('promotion') ? route('promotions.update', old('promotion')) : route('promotions.store') }}" data-busy data-promo-form data-edit-form>
            @csrf
            @method('PUT')
            <input type="hidden" name="form" value="edit">
            <input type="hidden" name="promotion" value="{{ old('promotion') }}">
            <div class="ui-filter-head">
                <strong>Edit promotion</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            @include('merchant.partials.promo-fields', ['form' => 'edit'])
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-delete-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('promotions.store') }}" data-busy data-delete-form>
            @csrf
            @method('DELETE')
            <div class="ui-filter-head">
                <strong>Delete promotion</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask">Are you sure you want to delete <strong data-delete-name>this promotion</strong>? This cannot be undone.</p>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" class="danger" data-loading="Deleting…"><span data-label>Delete</span></button>
            </div>
        </form>
    </div>

    <script type="application/json" id="promo-catalog">@json($catalog->map(fn ($product) => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku])->values())</script>
    <script>
        const catalog = JSON.parse(document.getElementById('promo-catalog').textContent);
        const closeMenus = () => {
            document.querySelectorAll('.more-menu').forEach((menu) => { menu.hidden = true; });
            document.querySelectorAll('[data-more]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        };
        const bindModal = (modal, openers) => {
            const open = () => { modal.hidden = false; };
            const close = () => { if (!modal.querySelector('button.is-busy')) modal.hidden = true; };
            openers.forEach((button) => button.addEventListener('click', open));
            modal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', close));
            return { open };
        };
        const bindPicker = (form) => {
            const search = form.querySelector('[data-product-search]');
            const hits = form.querySelector('[data-product-hits]');
            const chip = form.querySelector('[data-product-chip]');
            const hidden = form.querySelector('[name="productId"]');
            const choose = (product) => {
                hidden.value = product ? product.id : '';
                chip.hidden = !product;
                search.hidden = !!product;
                hits.hidden = true;
                search.value = '';
                if (product) chip.querySelector('[data-product-name]').textContent = product.name;
            };
            search.addEventListener('input', () => {
                const q = search.value.trim().toLowerCase();
                hits.replaceChildren();
                if (!q) { hits.hidden = true; return; }
                const matches = catalog.filter((product) => product.name.toLowerCase().includes(q) || String(product.sku || '').toLowerCase().includes(q)).slice(0, 8);
                if (!matches.length) {
                    const item = document.createElement('li');
                    item.className = 'muted';
                    item.textContent = 'No products found.';
                    hits.append(item);
                }
                matches.forEach((product) => {
                    const item = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button';
                    const name = document.createElement('span');
                    name.textContent = product.name;
                    const sku = document.createElement('span');
                    sku.className = 'ref';
                    sku.textContent = product.sku || '';
                    button.append(name, sku);
                    button.addEventListener('click', () => choose(product));
                    item.append(button);
                    hits.append(item);
                });
                hits.hidden = false;
            });
            chip.querySelector('[data-product-clear]').addEventListener('click', () => choose(null));
            form.querySelector('[name="discountType"]').addEventListener('change', () => {
                const value = form.querySelector('[name="discountValue"]');
                value.placeholder = form.querySelector('[name="discountType"]').value === 'FIXED' ? '0.00' : '10';
            });
            return choose;
        };

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
            if (!event.target.closest('[data-product-pick]')) {
                document.querySelectorAll('[data-product-hits]').forEach((list) => { list.hidden = true; });
            }
        });

        const createModal = document.querySelector('[data-create-modal]');
        const editModal = document.querySelector('[data-edit-modal]');
        const deleteModal = document.querySelector('[data-delete-modal]');
        bindModal(createModal, document.querySelectorAll('[data-promo-open]'));
        const edit = bindModal(editModal, []);
        const remove = bindModal(deleteModal, []);
        const editForm = editModal.querySelector('[data-edit-form]');
        const chooseEdit = bindPicker(editForm);
        bindPicker(createModal.querySelector('[data-promo-form]'));

        const setDate = (form, name, value) => {
            const input = form.querySelector(`[name="${name}"]`);
            input.value = value || '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };
        document.querySelectorAll('[data-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                closeMenus();
                editForm.action = button.dataset.url;
                editForm.querySelector('[name="promotion"]').value = button.dataset.id;
                editForm.querySelector('[data-field="name"]').value = button.dataset.name || '';
                editForm.querySelector('[data-field="value"]').value = button.dataset.value || '';
                const type = editForm.querySelector('[name="discountType"]');
                type.value = button.dataset.type || 'PERCENTAGE';
                type.dispatchEvent(new Event('change', { bubbles: true }));
                setDate(editForm, 'startDate', button.dataset.start);
                setDate(editForm, 'endDate', button.dataset.end);
                chooseEdit(button.dataset.product ? { id: button.dataset.product, name: button.dataset.productName } : null);
                edit.open();
            });
        });
        document.querySelectorAll('[data-remove]').forEach((button) => {
            button.addEventListener('click', () => {
                closeMenus();
                deleteModal.querySelector('[data-delete-form]').action = button.dataset.url;
                deleteModal.querySelector('[data-delete-name]').textContent = button.dataset.name;
                remove.open();
            });
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
            if (document.querySelector('.ui-modal:not([hidden]) .ui-calendar:not([hidden]), .ui-modal:not([hidden]) .ui-menu:not([hidden]), .ui-modal:not([hidden]) .product-hits:not([hidden])')) return;
            if (document.querySelector('.more-menu:not([hidden])')) {
                closeMenus();
                return;
            }
            [createModal, editModal, deleteModal].forEach((modal) => {
                if (modal && !modal.hidden && !modal.querySelector('button.is-busy')) modal.hidden = true;
            });
        });
    </script>
@endsection