@extends('layouts.app')

@section('title', 'Products | SalesDock')

@section('content')
    <div class="page">
        <div class="page-tools wrap">
            <span class="plan-pill @if ($full) full @endif">{{ $used }} / {{ $limit }} products</span>
            @foreach (['add' => 'Add', 'bulk' => 'Bulk', 'csv' => 'Import CSV', 'catalogue' => 'Catalogue'] as $key => $label)
                @if ($full)
                    <span class="tool">{{ $label }}</span>
                @else
                    <a class="tool @if ($panel === $key) on @endif" href="{{ request()->fullUrlWithQuery(['panel' => $panel === $key ? null : $key]) }}">{{ $label }}</a>
                @endif
            @endforeach
            @if (! $full)
                <a class="tool" href="{{ route('products.starter') }}">Starter pack</a>
            @endif
            <x-ui.filter :action="route('products')" :active="$filtered" label="Filter products">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Name, SKU, or barcode">
                </div>
                <x-ui.select name="category" label="Category" :value="$category" :options="['' => 'All categories'] + $categories->mapWithKeys(fn ($name) => [$name => $name])->all()" />
            </x-ui.filter>
        </div>

        @if ($panel === 'add' && ! $full)
            <section class="card">
                <p class="kicker">Add product</p>
                @include('merchant.partials.product-fields', [
                    'action' => route('products.store'),
                    'submit' => 'Save product',
                    'product' => null,
                ])
            </section>
        @endif

        @if ($panel === 'bulk' && ! $full)
            <section class="card">
                <p class="kicker">Add several</p>
                <form method="post" action="{{ route('products.bulk') }}">
                    @csrf
                    <div class="bulk">
                        @foreach (range(0, 4) as $index)
                            <label>Name <input name="rows[{{ $index }}][name]"></label>
                            <label>SKU <input name="rows[{{ $index }}][sku]" placeholder="Leave blank to generate"></label>
                            <label>Price <input type="number" name="rows[{{ $index }}][price]" step="0.01" min="0"></label>
                            <label>Stock <input type="number" name="rows[{{ $index }}][currentStock]" min="0"></label>
                            <div class="ui-field" data-select>
                                <span>Category</span>
                                <button type="button" class="ui-trigger" data-select-open aria-haspopup="listbox" aria-expanded="false">
                                    <span data-select-label>General</span>
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <input type="hidden" name="rows[{{ $index }}][category]" value="General" data-select-value>
                                <ul class="ui-menu" role="listbox" hidden data-select-menu>
                                    @foreach ($categories->prepend('General')->unique()->sort() as $cat)
                                        <li><button type="button" role="option" data-value="{{ $cat }}" @if ($cat === 'General') aria-selected="true" @endif>{{ $cat }}</button></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                    @include('components.ui.script')
                    <button type="submit">Save products</button>
                </form>
            </section>
        @endif

        @if ($panel === 'csv' && ! $full)
            <section class="card">
                <p class="kicker">Import CSV</p>
                <p class="muted">Name and price are required. The template has the other columns this import reads.</p>
                <form method="post" action="{{ route('products.csv') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="csv-file" data-csv>
                        <input type="file" name="file" accept=".csv,text/csv" required data-csv-input>
                        <button type="button" data-csv-open>Choose CSV</button>
                        <span data-csv-name>No file selected</span>
                        <a href="{{ route('products.template') }}">Download template</a>
                    </div>
                    <button type="submit">Import</button>
                </form>
            </section>
            <script>
                document.querySelectorAll('[data-csv]').forEach((box) => {
                    const input = box.querySelector('[data-csv-input]');
                    const name = box.querySelector('[data-csv-name]');
                    box.querySelector('[data-csv-open]').addEventListener('click', () => input.click());
                    input.addEventListener('change', () => {
                        const file = input.files && input.files[0];
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
            </script>
        @endif

        @if ($panel === 'catalogue' && ! $full)
            <section class="card">
                <p class="kicker">Catalogue</p>
                @if ($catalogueCategories->isEmpty())
                    <p class="muted">The catalogue has no items yet.</p>
                @else
                    <form method="get" action="{{ route('products') }}" id="cat-browse-form">
                        <input type="hidden" name="panel" value="catalogue">
                        <div class="ui-field" data-select>
                            <span>Category</span>
                            <button type="button" class="ui-trigger" data-select-open aria-haspopup="listbox" aria-expanded="false">
                                <span data-select-label>{{ $catalogueCat !== '' ? $catalogueCat : 'Choose a category…' }}</span>
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <input type="hidden" name="catalogue_cat" value="{{ $catalogueCat }}" data-select-value id="cat-browse-value">
                            <ul class="ui-menu" role="listbox" hidden data-select-menu>
                                @foreach ($catalogueCategories as $cat)
                                    <li>
                                        <button type="button" role="option" data-value="{{ $cat }}"
                                            @if ($catalogueCat === $cat) aria-selected="true" @endif>{{ $cat }}</button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </form>
                    @include('components.ui.script')
                    <script>
                        document.getElementById('cat-browse-value')?.addEventListener('change', () => {
                            document.getElementById('cat-browse-form')?.submit();
                        });
                    </script>

                    @if ($catalogueCat !== '')
                        <p class="muted" style="margin-top:0.5rem">
                            {{ $catalogue->count() }} item{{ $catalogue->count() === 1 ? '' : 's' }} in <strong>{{ $catalogueCat }}</strong>
                        </p>
                        @forelse ($catalogue as $item)
                            <form class="catalogue-row" method="post" action="{{ route('products.catalogue') }}">
                                @csrf
                                <input type="hidden" name="catalogueId" value="{{ $item->id }}">
                                <strong>{{ $item->name }}</strong>
                                <span>{{ $item->category ?: 'General' }}</span>
                                <label>Price <input type="number" name="price" step="0.01" min="0.01" required></label>
                                <label>Stock <input type="number" name="currentStock" min="0" value="0" required></label>
                                <button type="submit">Add</button>
                            </form>
                        @empty
                            <p class="muted">No items in this category.</p>
                        @endforelse
                    @endif
                @endif
            </section>
        @endif

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th class="num">Price</th>
                        <th class="num">Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php
                            $tone = (int) $product->currentStock <= 0 ? 'out' : ((int) $product->currentStock <= (int) $product->minThreshold ? 'low' : 'ok');
                            $stock = $tone === 'out' ? 'Out of stock' : ($tone === 'low' ? 'Low · '.$product->currentStock : $product->currentStock);
                        @endphp
                        <tr>
                            <td>
                                <a class="product-name" href="{{ route('products.show', $product) }}">
                                    @if ($product->imageUrl)
                                        <img src="{{ $product->imageUrl }}" alt="">
                                    @endif
                                    {{ $product->name }}
                                </a>
                            </td>
                            <td>{{ $product->sku }}</td>
                            <td>{{ $product->category }}</td>
                            <td class="num">₦{{ number_format((float) $product->price, 2) }}</td>
                            <td class="num stock {{ $tone }}">{{ $stock }}</td>
                            <td><span class="badge active">Active</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="6">No products found.</td>
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
@endsection
