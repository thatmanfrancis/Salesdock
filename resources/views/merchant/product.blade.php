@extends('layouts.app')

@section('title', $product->name.' | SalesDock')

@section('content')
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('products') }}">Back</a>
            <strong>{{ $product->name }}</strong>
            <span class="badge {{ $product->isActive ? 'active' : 'cancelled' }}">{{ $product->isActive ? 'Active' : 'Inactive' }}</span>
            @if ($product->isActive)
                <form method="post" action="{{ route('products.deactivate', $product) }}">
                    @csrf
                    <button class="quiet" type="submit">Deactivate</button>
                </form>
            @endif
        </div>

        <nav class="tabs">
            @foreach (['info' => 'Info', 'variants' => 'Variants', 'stock' => 'Stock history', 'promo' => 'Promotion'] as $key => $label)
                <a href="{{ route('products.show', $product) }}?tab={{ $key }}" @class(['on' => $tab === $key])>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($tab === 'info')
            <section class="card">
                <p class="kicker">Edit product</p>
                @include('merchant.partials.product-fields', [
                    'action' => route('products.update', $product),
                    'method' => 'PUT',
                    'submit' => 'Update product',
                    'stock' => false,
                ])
            </section>
        @endif

        @if ($tab === 'variants')
            <section class="card orders">
                <table>
                    <thead>
                        <tr>
                            <th>Variant</th>
                            <th>SKU</th>
                            <th class="num">Price</th>
                            @if ($costs)<th class="num">Cost</th>@endif
                            <th class="num">Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($variants as $variant)
                            <tr>
                                <td>{{ $variant->name }}</td>
                                <td>{{ $variant->sku }}</td>
                                <td class="num">₦{{ number_format((float) $variant->price, 2) }}</td>
                                @if ($costs)<td class="num">₦{{ number_format((float) $variant->costPrice, 2) }}</td>@endif
                                <td class="num">{{ $variant->currentStock }}</td>
                            </tr>
                        @empty
                            <tr><td class="empty" colspan="{{ $costs ? 5 : 4 }}">No variants yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
            <section class="card">
                <p class="kicker">Add variant</p>
                <form method="post" action="{{ route('products.variants.store', $product) }}">
                    @csrf
                    <div class="product-fields">
                        <label>Name <input name="name" required></label>
                        <label>SKU <input name="sku" required></label>
                        <label>Barcode <input name="barcode"></label>
                        <label>Price <input type="number" name="price" step="0.01" min="0" required></label>
                        @if ($costs)
                            <label>Cost <input type="number" name="costPrice" step="0.01" min="0"></label>
                        @endif
                        <label>Stock <input type="number" name="currentStock" min="0" value="0"></label>
                    </div>
                    <button type="submit">Add variant</button>
                </form>
            </section>
        @endif

        @if ($tab === 'stock')
            @if ($branches !== [])
                <section class="card orders branch-levels">
                    <p class="kicker band">Stock by branch</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th class="num">On hand</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($branches as $branchId => $branchName)
                                @php $level = (int) ($branchQty[$branchId] ?? 0); @endphp
                                <tr>
                                    <td>{{ $branchName }}</td>
                                    <td class="num stock {{ $level <= 0 ? 'out' : ($level <= (int) $product->minThreshold ? 'low' : 'ok') }}">{{ $level }}</td>
                                </tr>
                            @endforeach
                            <tr class="total">
                                <td><strong>All branches</strong></td>
                                <td class="num"><strong>{{ $product->currentStock }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            @endif
            <section class="card">
                <p class="kicker">Adjust stock</p>
                <p class="muted">{{ $product->currentStock }} on hand{{ $branches !== [] ? ' across all branches' : '' }}. {{ max(0, (int) $product->currentStock - (int) $product->reservedQty) }} free to sell.</p>
                <form method="post" action="{{ route('products.stock', $product) }}">
                    @csrf
                    <div class="product-fields">
                        @if ($branches !== [])
                            <x-ui.select name="branchId" label="Branch" :value="array_key_first($branches)" :options="$branches" />
                        @endif
                        <x-ui.select name="type" label="Movement" value="ADJUSTMENT_IN" :options="['ADJUSTMENT_IN' => 'Stock in', 'ADJUSTMENT_OUT' => 'Stock out']" />
                        <label>Quantity <input type="number" name="quantity" min="1" required></label>
                        <label class="wide">Notes <input name="notes"></label>
                    </div>
                    <button type="submit">Update stock</button>
                </form>
            </section>
            @if ($branches !== [])
                @php
                    $fromDefault = collect($branches)->keys()->first(fn ($id) => ($branchQty[$id] ?? 0) > 0) ?? array_key_first($branches);
                    $toDefault = collect($branches)->keys()->first(fn ($id) => $id !== $fromDefault);
                @endphp
                <section class="card">
                    <p class="kicker">Move stock between branches</p>
                    <form method="post" action="{{ route('products.transfer', $product) }}">
                        @csrf
                        <div class="product-fields">
                            <x-ui.select name="fromBranchId" label="From" :value="old('fromBranchId', $fromDefault)" :options="$branches" />
                            <x-ui.select name="toBranchId" label="To" :value="old('toBranchId', $toDefault)" :options="$branches" />
                            <label>Quantity <input type="number" name="quantity" min="1" value="{{ old('quantity') }}" required></label>
                            <label class="wide">Notes <input name="notes" value="{{ old('notes') }}" maxlength="255" placeholder="Optional, e.g. driver or waybill"></label>
                        </div>
                        <button type="submit">Move stock</button>
                    </form>
                </section>
            @endif
            <section class="card orders">
                <table>
                    <thead>
                        <tr>
                            <th>When</th>
                            @if ($branches !== [])<th>Branch</th>@endif
                            <th>Type</th>
                            <th class="num">Qty</th>
                            <th class="num">Before</th>
                            <th class="num">After</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            <tr>
                                <td>{{ $movement->createdAt?->format('d M H:i') }}</td>
                                @if ($branches !== [])<td>{{ $branches[$movement->branchId] ?? '—' }}</td>@endif
                                <td>{{ str_replace('_', ' ', $movement->type) }}</td>
                                <td class="num">{{ $movement->quantity }}</td>
                                <td class="num">{{ $movement->beforeQty }}</td>
                                <td class="num">{{ $movement->afterQty }}</td>
                                <td>{{ $movement->notes ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td class="empty" colspan="{{ $branches !== [] ? 7 : 6 }}">No stock movements yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endif

        @if ($tab === 'promo')
            <section class="card">
                <p class="kicker">Promotion</p>
                @if ($promo)
                    <p class="muted">{{ $promo->name }} is attached to this product.</p>
                @endif
                <form method="post" action="{{ route('products.promo', $product) }}">
                    @csrf
                    <x-ui.select name="activePromoId" label="Promotion" :value="$product->activePromoId ?? ''" :options="['' => 'None'] + $promos->all()" />
                    <button type="submit">Save promotion</button>
                </form>
            </section>
        @endif
    </div>
@endsection
