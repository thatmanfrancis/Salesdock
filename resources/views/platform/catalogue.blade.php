@extends('layouts.app')

@section('title', 'Catalogue | SalesDock')

@php
    $reopen = old('form') === 'item' && $errors->any();
    $reopenCategory = old('form') === 'category' && $errors->any();
    $reopenRename = old('form') === 'rename' && $errors->any();
    $categoryNames = collect($categoryRows)->pluck('name');
    $pickedCategory = old('category', '');
@endphp

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Catalogue</h1>
                <span class="tally">{{ number_format($total) }} total</span>
            </div>
            <div class="row-actions">
                <x-ui.filter :action="route('admin.catalogue')" :active="$filtered" label="Filter catalogue">
                    <div class="ui-field">
                        <span>Search</span>
                        <input type="search" name="q" value="{{ $q }}" placeholder="Name, brand, or barcode" maxlength="80">
                    </div>
                    <x-ui.select name="category" label="Category" :value="$category" :options="$categories" />
                </x-ui.filter>
                <button type="button" data-add-category>Add category</button>
                <button type="button" data-create>New item</button>
            </div>
        </div>
        <p class="muted">Shops search this list and add a product with their own price and stock.</p>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Items</th>
                        <th>Shops</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categoryRows as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ number_format($row['items']) }}</td>
                            <td>{{ number_format($row['stores']) }}</td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="detail" data-category-detail='@json($row)'>Details</button>
                                    <button type="button" class="detail" data-rename data-name="{{ $row['name'] }}">Rename</button>
                                    @if ($row['stores'] === 0)
                                        <button type="button" class="quiet" data-ask data-url="{{ route('admin.catalogue.categories.destroy') }}" data-name="{{ $row['name'] }}" data-title="Delete category" data-copy="Delete {{ $row['name'] }}? Catalogue items in it become uncategorised." data-label="Delete" data-loading="Deleting…" data-tone="danger">Delete</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="4">No categories.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Brand</th>
                        <th>Unit</th>
                        <th>Barcode</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php $group = null; @endphp
                    @forelse ($items as $item)
                        @php $label = $item->category ?: 'Uncategorised'; @endphp
                        @if ($group !== $label)
                            @php $group = $label; @endphp
                            <tr class="group">
                                <td colspan="5">{{ $label }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td>
                                {{ $item->name }}
                                @unless ($item->isActive)
                                    <span class="badge draft">Hidden</span>
                                @endunless
                            </td>
                            <td>{{ $item->brand ?: '—' }}</td>
                            <td>{{ $item->unit ?: '—' }}</td>
                            <td>{{ $item->barcode ?: '—' }}</td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="detail" data-edit data-url="{{ route('admin.catalogue.update', $item) }}" data-name="{{ $item->name }}" data-brand="{{ $item->brand }}" data-category="{{ $item->category }}" data-unit="{{ $item->unit }}" data-barcode="{{ $item->barcode }}" data-description="{{ $item->description }}" data-active="{{ $item->isActive ? '1' : '0' }}">Edit</button>
                                    <button type="button" class="quiet" data-ask data-url="{{ route('admin.catalogue.destroy', $item) }}" data-title="Delete item" data-copy="Delete {{ $item->name }} from the catalogue?" data-label="Delete" data-loading="Deleting…" data-tone="danger">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="5">No catalogue items.</td>
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

    <div class="ui-modal" data-item-modal @unless ($reopen) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet tall" method="post" action="{{ $reopen && old('item') ? route('admin.catalogue.update', old('item')) : route('admin.catalogue.store') }}" data-busy>
            @csrf
            <input type="hidden" name="_method" value="{{ $reopen && old('item') ? 'PUT' : 'POST' }}" data-item-method>
            <input type="hidden" name="form" value="item">
            <input type="hidden" name="item" value="{{ old('item') }}">
            <div class="ui-filter-head">
                <strong data-item-title>{{ $reopen && old('item') ? 'Edit item' : 'New item' }}</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <div class="ui-field">
                <span>Name</span>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="255">
            </div>
            <div class="ui-modal-grid">
                <div class="ui-field">
                    <span>Category</span>
                    <select name="category">
                        <option value="">Uncategorised</option>
                        @foreach ($categoryNames as $name)
                            <option value="{{ $name }}" @selected($pickedCategory === $name)>{{ $name }}</option>
                        @endforeach
                        @if ($pickedCategory !== '' && ! $categoryNames->contains($pickedCategory))
                            <option value="{{ $pickedCategory }}" selected>{{ $pickedCategory }}</option>
                        @endif
                    </select>
                </div>
                <div class="ui-field">
                    <span>Brand</span>
                    <input type="text" name="brand" value="{{ old('brand') }}" maxlength="120">
                </div>
                <div class="ui-field">
                    <span>Unit</span>
                    <input type="text" name="unit" value="{{ old('unit') }}" maxlength="80" placeholder="per pack">
                </div>
                <div class="ui-field">
                    <span>Barcode</span>
                    <input type="text" name="barcode" value="{{ old('barcode') }}" maxlength="80">
                </div>
            </div>
            <div class="ui-field">
                <span>Description</span>
                <textarea name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea>
            </div>
            <label class="check">
                <input type="checkbox" name="isActive" value="1" @checked(old('form') === 'item' ? old('isActive') : true)>
                <span>Visible to shops</span>
            </label>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-ask-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('admin.catalogue') }}" data-busy>
            @csrf
            <input type="hidden" name="_method" value="DELETE">
            <input type="hidden" name="name" value="">
            <div class="ui-filter-head">
                <strong data-ask-title>Are you sure?</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask" data-ask-copy></p>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" class="danger" data-loading="Deleting…"><span data-label>Delete</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-category-modal @unless ($reopenCategory) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('admin.catalogue.categories.store') }}" data-busy>
            @csrf
            <input type="hidden" name="form" value="category">
            <div class="ui-filter-head">
                <strong>New category</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <div class="ui-field">
                <span>Name</span>
                <input type="text" name="name" value="{{ $reopenCategory ? old('name') : '' }}" required maxlength="120">
            </div>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-rename-modal @unless ($reopenRename) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('admin.catalogue.categories.update') }}" data-busy>
            @csrf
            @method('PUT')
            <input type="hidden" name="form" value="rename">
            <input type="hidden" name="from" value="{{ old('from') }}">
            <div class="ui-filter-head">
                <strong>Rename category</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <div class="ui-field">
                <span>Name</span>
                <input type="text" name="name" value="{{ $reopenRename ? old('name') : '' }}" required maxlength="120">
            </div>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-detail-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <div class="ui-modal-sheet">
            <div class="ui-filter-head">
                <strong data-detail-name>Category</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask" data-detail-counts></p>
            <ul class="shop-list" data-detail-shops></ul>
        </div>
    </div>

    <script>
        const closeModal = (box) => { if (box && !box.querySelector('button.is-busy')) box.hidden = true; };

        // ── Item modal (create / edit) ────────────────────────────────────────
        const itemModal = document.querySelector('[data-item-modal]');
        const itemForm = itemModal.querySelector('form');
        const itemMethod = itemForm.querySelector('[data-item-method]');
        const itemTitle = itemModal.querySelector('[data-item-title]');
        const itemSubmit = itemForm.querySelector('[type="submit"]');
        const fill = (values) => {
            ['name', 'brand', 'category', 'unit', 'barcode', 'description', 'item'].forEach((name) => {
                const field = itemForm.querySelector('[name="' + name + '"]');
                if (field) field.value = values[name] || '';
            });
            itemForm.querySelector('[name="isActive"]').checked = values.active !== '0';
        };
        document.querySelector('[data-create]').addEventListener('click', () => {
            itemForm.action = @json(route('admin.catalogue.store'));
            itemMethod.value = 'POST';
            itemTitle.textContent = 'New item';
            fill({ active: '1' });
            itemModal.hidden = false;
        });
        document.querySelectorAll('[data-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                itemForm.action = button.dataset.url;
                itemMethod.value = 'PUT';
                itemTitle.textContent = 'Edit item';
                fill({
                    item: button.dataset.url.split('/').pop(),
                    name: button.dataset.name,
                    brand: button.dataset.brand,
                    category: button.dataset.category,
                    unit: button.dataset.unit,
                    barcode: button.dataset.barcode,
                    description: button.dataset.description,
                    active: button.dataset.active,
                });
                itemModal.hidden = false;
            });
        });
        itemModal.querySelectorAll('[data-close]').forEach((btn) => btn.addEventListener('click', () => closeModal(itemModal)));

        // ── Confirm (ask) modal — used for item & category deletes ────────────
        const askModal = document.querySelector('[data-ask-modal]');
        const askForm = askModal.querySelector('form');
        const askNameField = askForm.querySelector('[name="name"]');
        const askSubmit = askForm.querySelector('[type="submit"]');
        document.querySelectorAll('[data-ask]').forEach((button) => {
            button.addEventListener('click', () => {
                askForm.action = button.dataset.url;
                askModal.querySelector('[data-ask-title]').textContent = button.dataset.title || 'Are you sure?';
                askModal.querySelector('[data-ask-copy]').textContent = button.dataset.copy || '';
                askSubmit.dataset.loading = button.dataset.loading || 'Deleting…';
                askSubmit.querySelector('[data-label]').textContent = button.dataset.label || 'Delete';
                // Pass name for category deletes (ignored for item deletes)
                if (askNameField) askNameField.value = button.dataset.name || '';
                askModal.hidden = false;
            });
        });
        askModal.querySelectorAll('[data-close]').forEach((btn) => btn.addEventListener('click', () => closeModal(askModal)));

        // ── Add category modal ────────────────────────────────────────────────
        const categoryModal = document.querySelector('[data-category-modal]');
        document.querySelector('[data-add-category]').addEventListener('click', () => {
            const input = categoryModal.querySelector('[name="name"]');
            input.value = '';
            categoryModal.hidden = false;
            input.focus();
        });
        categoryModal.querySelectorAll('[data-close]').forEach((btn) => btn.addEventListener('click', () => closeModal(categoryModal)));

        // ── Rename category modal ─────────────────────────────────────────────
        const renameModal = document.querySelector('[data-rename-modal]');
        document.querySelectorAll('[data-rename]').forEach((button) => {
            button.addEventListener('click', () => {
                const nameInput = renameModal.querySelector('[name="name"]');
                const fromInput = renameModal.querySelector('[name="from"]');
                const currentName = button.dataset.name || '';
                fromInput.value = currentName;
                nameInput.value = currentName;
                renameModal.hidden = false;
                nameInput.select();
            });
        });
        renameModal.querySelectorAll('[data-close]').forEach((btn) => btn.addEventListener('click', () => closeModal(renameModal)));

        // ── Category details modal ────────────────────────────────────────────
        const detailModal = document.querySelector('[data-detail-modal]');
        const detailName = detailModal.querySelector('[data-detail-name]');
        const detailCounts = detailModal.querySelector('[data-detail-counts]');
        const detailShops = detailModal.querySelector('[data-detail-shops]');
        document.querySelectorAll('[data-category-detail]').forEach((button) => {
            button.addEventListener('click', () => {
                const row = JSON.parse(button.dataset.categoryDetail);
                detailName.textContent = row.name;
                const itemWord = row.items === 1 ? 'item' : 'items';
                const storeWord = row.stores === 1 ? 'store' : 'stores';
                detailCounts.textContent = row.items + ' catalogue ' + itemWord + ' · ' + row.stores + ' connected ' + storeWord + '.';
                detailShops.innerHTML = '';
                if (row.shops && row.shops.length > 0) {
                    row.shops.forEach((shop) => {
                        const li = document.createElement('li');
                        const a = document.createElement('a');
                        a.href = shop.url;
                        a.textContent = shop.name;
                        li.appendChild(a);
                        detailShops.appendChild(li);
                    });
                    if (row.stores > row.shops.length) {
                        const li = document.createElement('li');
                        li.className = 'muted';
                        li.textContent = '…and ' + (row.stores - row.shops.length) + ' more';
                        detailShops.appendChild(li);
                    }
                }
                detailModal.hidden = false;
            });
        });
        detailModal.querySelectorAll('[data-close]').forEach((btn) => btn.addEventListener('click', () => closeModal(detailModal)));

        // ── Busy state on all forms ───────────────────────────────────────────
        document.querySelectorAll('form[data-busy]').forEach((box) => {
            box.addEventListener('submit', () => {
                const button = box.querySelector('[type="submit"]');
                const label = button ? button.querySelector('[data-label]') : null;
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                if (label) label.textContent = button.dataset.loading || 'Saving…';
            });
        });

        // ── Escape key closes any open modal ─────────────────────────────────
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            [itemModal, askModal, categoryModal, renameModal, detailModal].forEach((box) => {
                if (box && !box.hidden) closeModal(box);
            });
        });
    </script>
@endsection
