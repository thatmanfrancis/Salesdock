@extends('layouts.app')

@section('title', 'Catalogue | SalesDock')

@php
    $reopenItem = old('form') === 'item' && $errors->any();
    $reopenBulk = old('form') === 'bulk' && $errors->any();
    $reopenCsv = $errors->has('file');
    $reopenCategory = old('form') === 'category' && $errors->any();
    $reopenCategoriesBulk = old('form') === 'categories-bulk' && $errors->any();
    $reopenRename = old('form') === 'rename' && $errors->any();
    $openItems = $reopenItem || $reopenBulk || $reopenCsv;
    $openCategories = $reopenCategory || $reopenCategoriesBulk;
    $itemTab = $reopenBulk ? 'many' : ($reopenCsv ? 'csv' : 'one');
    $categoryTab = $reopenCategoriesBulk ? 'many' : 'one';
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

    {{-- New / edit item: tabs for One, Multiple, CSV (tabs hidden while editing) --}}
    <div class="ui-modal" data-item-modal @unless ($openItems) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <div class="ui-modal-sheet tall wide">
            <div class="ui-filter-head">
                <strong data-item-title>{{ $reopenItem && old('item') ? 'Edit item' : 'New item' }}</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <nav class="modal-tabs" data-item-tabs aria-label="How to add items" @if ($reopenItem && old('item')) hidden @endif>
                <button type="button" data-tab="one" @class(['on' => $itemTab === 'one'])>One item</button>
                <button type="button" data-tab="many" @class(['on' => $itemTab === 'many'])>Multiple</button>
                <button type="button" data-tab="csv" @class(['on' => $itemTab === 'csv'])>Import CSV</button>
            </nav>

            <form method="post" action="{{ $reopenItem && old('item') ? route('admin.catalogue.update', old('item')) : route('admin.catalogue.store') }}" data-busy data-panel="one" @unless ($itemTab === 'one' || ($reopenItem && old('item'))) hidden @endunless>
                @csrf
                <input type="hidden" name="_method" value="{{ $reopenItem && old('item') ? 'PUT' : 'POST' }}" data-item-method>
                <input type="hidden" name="form" value="item">
                <input type="hidden" name="item" value="{{ old('item') }}">
                <div class="ui-field">
                    <span>Name <span class="req">*</span></span>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="255">
                </div>
                <div class="ui-modal-grid">
                    <div class="ui-field">
                        <span>Category <span class="req">*</span></span>
                        <select name="category" required>
                            <option value="" disabled @selected($pickedCategory === '')>Choose a category</option>
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

            <form method="post" action="{{ route('admin.catalogue.bulk') }}" data-busy data-panel="many" @unless ($itemTab === 'many') hidden @endunless>
                @csrf
                <input type="hidden" name="form" value="bulk">
                <p class="muted">Fill a row, then Add another. Empty rows are skipped. Name and category are required on every filled row.</p>
                <div class="catalogue-bulk" data-bulk-rows>
                    <div class="catalogue-bulk-row">
                        <div class="ui-field"><span>Name <span class="req">*</span></span><input type="text" name="rows[0][name]" maxlength="255" required></div>
                        <div class="ui-field"><span>Category <span class="req">*</span></span><input type="text" name="rows[0][category]" list="catalogue-category-list" maxlength="120" required></div>
                        <div class="ui-field"><span>Brand</span><input type="text" name="rows[0][brand]" maxlength="120"></div>
                        <div class="ui-field"><span>Unit</span><input type="text" name="rows[0][unit]" maxlength="80" placeholder="per pack"></div>
                        <div class="ui-field"><span>Barcode</span><input type="text" name="rows[0][barcode]" maxlength="80"></div>
                        <div class="ui-field wide"><span>Description</span><input type="text" name="rows[0][description]" maxlength="2000"></div>
                    </div>
                </div>
                <button type="button" class="quiet" data-bulk-add>+ Add another</button>
                <div class="ui-modal-actions">
                    <button type="button" data-close>Cancel</button>
                    <button type="submit" data-loading="Saving…"><span data-label>Save all</span></button>
                </div>
            </form>

            <form method="post" action="{{ route('admin.catalogue.import') }}" enctype="multipart/form-data" data-busy data-panel="csv" @unless ($itemTab === 'csv') hidden @endunless>
                @csrf
                <p class="muted">Name and category are required on every row. An optional SKU is kept when shops import the item. Existing items and duplicate SKUs are skipped. Download the template for the supported columns.</p>
                <div class="csv-file" data-csv>
                    <input type="file" name="file" accept=".csv,text/csv" required data-csv-input>
                    <button type="button" data-csv-open>Choose CSV</button>
                    <span data-csv-name>No file selected</span>
                    <a href="{{ route('admin.catalogue.template') }}">Download template</a>
                </div>
                <div class="ui-modal-actions">
                    <button type="button" data-close>Cancel</button>
                    <button type="submit" data-loading="Importing…"><span data-label>Import</span></button>
                </div>
            </form>
        </div>
    </div>

    {{-- Add category: tabs for One / Multiple --}}
    <div class="ui-modal" data-category-modal @unless ($openCategories) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <div class="ui-modal-sheet tall">
            <div class="ui-filter-head">
                <strong>Add category</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <nav class="modal-tabs" data-category-tabs aria-label="How to add categories">
                <button type="button" data-tab="one" @class(['on' => $categoryTab === 'one'])>One category</button>
                <button type="button" data-tab="many" @class(['on' => $categoryTab === 'many'])>Multiple</button>
            </nav>

            <form method="post" action="{{ route('admin.catalogue.categories.store') }}" data-busy data-panel="one" @unless ($categoryTab === 'one') hidden @endunless>
                @csrf
                <input type="hidden" name="form" value="category">
                <div class="ui-field">
                    <span>Name <span class="req">*</span></span>
                    <input type="text" name="name" value="{{ $reopenCategory ? old('name') : '' }}" required maxlength="120">
                </div>
                <div class="ui-modal-actions">
                    <button type="button" data-close>Cancel</button>
                    <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
                </div>
            </form>

            <form method="post" action="{{ route('admin.catalogue.categories.bulk') }}" data-busy data-panel="many" @unless ($categoryTab === 'many') hidden @endunless>
                @csrf
                <input type="hidden" name="form" value="categories-bulk">
                <p class="muted">Type a category name, then Add another. Duplicates are skipped. Name is required.</p>
                <div class="catalogue-cat-bulk" data-cat-rows>
                    <div class="ui-field" data-cat-row>
                        <span>Name <span class="req">*</span></span>
                        <input type="text" name="names[0]" maxlength="120" required>
                    </div>
                </div>
                <button type="button" class="quiet" data-cat-add>+ Add another</button>
                <div class="ui-modal-actions">
                    <button type="button" data-close>Cancel</button>
                    <button type="submit" data-loading="Saving…"><span data-label>Save all</span></button>
                </div>
            </form>
        </div>
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
                <span>Name <span class="req">*</span></span>
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

    <datalist id="catalogue-category-list">
        @foreach ($categoryNames as $name)
            <option value="{{ $name }}"></option>
        @endforeach
    </datalist>

    <script>
        const closeModal = (box) => { if (box && !box.querySelector('button.is-busy')) box.hidden = true; };

        const switchTab = (modal, tab) => {
            modal.querySelectorAll('.modal-tabs [data-tab]').forEach((btn) => btn.classList.toggle('on', btn.dataset.tab === tab));
            modal.querySelectorAll('[data-panel]').forEach((panel) => { panel.hidden = panel.dataset.panel !== tab; });
        };

        // ── Item modal ────────────────────────────────────────────────────────
        const itemModal = document.querySelector('[data-item-modal]');
        const itemTabs = itemModal.querySelector('[data-item-tabs]');
        const itemForm = itemModal.querySelector('[data-panel="one"]');
        const itemMethod = itemForm.querySelector('[data-item-method]');
        const itemTitle = itemModal.querySelector('[data-item-title]');
        const fill = (values) => {
            ['name', 'brand', 'category', 'unit', 'barcode', 'description', 'item'].forEach((name) => {
                const field = itemForm.querySelector('[name="' + name + '"]');
                if (field) field.value = values[name] || '';
            });
            itemForm.querySelector('[name="isActive"]').checked = values.active !== '0';
        };
        const openCreateItem = (tab = 'one') => {
            itemForm.action = @json(route('admin.catalogue.store'));
            itemMethod.value = 'POST';
            itemTitle.textContent = 'New item';
            fill({ active: '1' });
            itemTabs.hidden = false;
            switchTab(itemModal, tab);
            itemModal.hidden = false;
        };
        document.querySelector('[data-create]').addEventListener('click', () => openCreateItem('one'));
        itemTabs.querySelectorAll('[data-tab]').forEach((btn) => {
            btn.addEventListener('click', () => switchTab(itemModal, btn.dataset.tab));
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
                itemTabs.hidden = true;
                switchTab(itemModal, 'one');
                itemModal.hidden = false;
            });
        });
        itemModal.querySelectorAll('[data-close]').forEach((btn) => btn.addEventListener('click', () => closeModal(itemModal)));

        const bulkRows = itemModal.querySelector('[data-bulk-rows]');
        const bulkTemplate = bulkRows.firstElementChild.cloneNode(true);
        const bulkForm = itemModal.querySelector('[data-panel="many"]');
        itemModal.querySelector('[data-bulk-add]').addEventListener('click', () => {
            const index = bulkRows.children.length;
            const row = bulkTemplate.cloneNode(true);
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/rows\[\d+]/, 'rows[' + index + ']');
                field.value = '';
            });
            bulkRows.appendChild(row);
            row.querySelector('input')?.focus();
        });
        bulkForm.addEventListener('submit', () => {
            [...bulkRows.children].forEach((row) => {
                const fields = [...row.querySelectorAll('input')];
                const empty = fields.every((field) => field.value.trim() === '');
                if (empty && bulkRows.children.length > 1) {
                    row.remove();
                    return;
                }
                fields.forEach((field) => {
                    if (field.name.includes('[name]') || field.name.includes('[category]')) {
                        field.required = !empty;
                    }
                });
            });
            [...bulkRows.children].forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/rows\[\d+]/, 'rows[' + index + ']');
                });
            });
        });
        itemModal.querySelectorAll('[data-csv]').forEach((box) => {
            const input = box.querySelector('[data-csv-input]');
            const name = box.querySelector('[data-csv-name]');
            box.querySelector('[data-csv-open]').addEventListener('click', () => input.click());
            input.addEventListener('change', () => {
                const file = input.files[0];
                if (!file) {
                    name.textContent = 'No file selected';
                    return;
                }
                if (!file.name.toLowerCase().endsWith('.csv')) {
                    input.value = '';
                    name.textContent = 'Choose a .csv file.';
                    return;
                }
                name.textContent = file.name;
            });
        });

        // ── Category modal ────────────────────────────────────────────────────
        const categoryModal = document.querySelector('[data-category-modal]');
        document.querySelector('[data-add-category]').addEventListener('click', () => {
            categoryModal.querySelector('[data-panel="one"] [name="name"]').value = '';
            switchTab(categoryModal, 'one');
            categoryModal.hidden = false;
            categoryModal.querySelector('[data-panel="one"] [name="name"]').focus();
        });
        categoryModal.querySelectorAll('[data-category-tabs] [data-tab]').forEach((btn) => {
            btn.addEventListener('click', () => switchTab(categoryModal, btn.dataset.tab));
        });
        const catRows = categoryModal.querySelector('[data-cat-rows]');
        const catTemplate = catRows.firstElementChild.cloneNode(true);
        const catBulkForm = categoryModal.querySelector('[data-panel="many"]');
        categoryModal.querySelector('[data-cat-add]').addEventListener('click', () => {
            const index = catRows.children.length;
            const row = catTemplate.cloneNode(true);
            const input = row.querySelector('input');
            input.name = 'names[' + index + ']';
            input.value = '';
            catRows.appendChild(row);
            input.focus();
        });
        catBulkForm.addEventListener('submit', () => {
            [...catRows.children].forEach((row) => {
                const input = row.querySelector('input');
                if (input.value.trim() === '' && catRows.children.length > 1) {
                    row.remove();
                } else {
                    input.required = true;
                }
            });
            [...catRows.children].forEach((row, index) => {
                row.querySelector('input').name = 'names[' + index + ']';
            });
        });
        categoryModal.querySelectorAll('[data-close]').forEach((btn) => btn.addEventListener('click', () => closeModal(categoryModal)));

        // ── Confirm delete ────────────────────────────────────────────────────
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
                if (askNameField) askNameField.value = button.dataset.name || '';
                askModal.hidden = false;
            });
        });
        askModal.querySelectorAll('[data-close]').forEach((btn) => btn.addEventListener('click', () => closeModal(askModal)));

        // ── Rename ────────────────────────────────────────────────────────────
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

        // ── Category details ──────────────────────────────────────────────────
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

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            [itemModal, askModal, categoryModal, renameModal, detailModal].forEach((box) => {
                if (box && !box.hidden) closeModal(box);
            });
        });
    </script>
@endsection
