@php
    $selectedCategory = old('category', $product->category ?? 'General');
    $choices = collect($categories ?? [])
        ->push($product->category ?? 'General')
        ->filter(fn ($name) => is_string($name) && $name !== '' && $name !== '__new__')
        ->unique()
        ->sort()
        ->values();
@endphp
<form method="post" action="{{ $action }}" enctype="multipart/form-data" data-product-form data-sku-url="{{ route('products.sku') }}" data-sku-except="{{ $product->id ?? '' }}">
    @csrf
    @isset($method) @method($method) @endisset
    <div class="product-fields">
        <label>Name <span class="req">*</span> <input name="name" value="{{ old('name', $product->name ?? '') }}" required data-product-name></label>
        <label>SKU <span class="req">*</span>
            <input name="sku" value="{{ old('sku', $product->sku ?? '') }}" required data-sku @if ($product) data-sku-lock @endif>
        </label>
        <label>Barcode <input name="barcode" value="{{ old('barcode', $product->barcode ?? '') }}"></label>
        <label>Category
            <select name="category" data-category>
                @foreach ($choices as $name)
                    <option value="{{ $name }}" @selected($selectedCategory === $name)>{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label>Subcategory <input name="subCategory" value="{{ old('subCategory', $product->subCategory ?? '') }}"></label>
        <label class="wide">Photo
            <span class="csv-file">
                @if ($product?->imageUrl)
                    <img class="shot" src="{{ $product->imageUrl }}" alt="">
                @endif
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-photo>
                <button type="button" data-photo-open>Choose photo</button>
                <span data-photo-name>{{ $product?->imageUrl ? 'Current photo stays until you choose another. JPG, PNG, or WebP, 500KB.' : 'Optional. JPG, PNG, or WebP, 500KB.' }}</span>
            </span>
        </label>
        <label class="wide">Description <textarea name="description">{{ old('description', $product->description ?? '') }}</textarea></label>
        <label>Price <span class="req">*</span> <input type="number" name="price" step="0.01" min="0" value="{{ old('price', $product->price ?? '') }}" required></label>
        <label>Original price <input type="number" name="originalPrice" step="0.01" min="0" value="{{ old('originalPrice', $product->originalPrice ?? '') }}"></label>
        @if ($costs)
            <label>Cost price <input type="number" name="costPrice" step="0.01" min="0" value="{{ old('costPrice', $product->costPrice ?? 0) }}"></label>
            <label>Wholesale price <input type="number" name="wholesalePrice" step="0.01" min="0" value="{{ old('wholesalePrice', $product->wholesalePrice ?? '') }}"></label>
        @endif
        <label>VAT override % <input type="number" name="vatRateOverride" step="0.01" min="0" value="{{ old('vatRateOverride', $product->vatRateOverride ?? '') }}"></label>
        @if ($stock ?? true)
            <label>Stock <input type="number" name="currentStock" min="0" value="{{ old('currentStock', $product->currentStock ?? 0) }}"></label>
        @endif
        <label>Low-stock threshold <input type="number" name="minThreshold" min="0" value="{{ old('minThreshold', $product->minThreshold ?? 5) }}"></label>
        <label>Reorder quantity <input type="number" name="reorderQty" min="0" value="{{ old('reorderQty', $product->reorderQty ?? 0) }}"></label>
        <label>Lead time (days) <input type="number" name="leadTimeDays" min="0" value="{{ old('leadTimeDays', $product->leadTimeDays ?? 0) }}"></label>
        <x-ui.select name="availabilityMode" label="Availability" :value="old('availabilityMode', $product->availabilityMode ?? 'BOTH')" :options="['BOTH' => 'POS and online', 'POS_ONLY' => 'POS only', 'ONLINE_ONLY' => 'Online only', 'DISABLED' => 'Disabled']" />
        <x-ui.select name="supplierId" label="Supplier" :value="old('supplierId', $product->supplierId ?? '')" :options="['' => 'None'] + $suppliers->all()" />
        <x-ui.date name="expiresAt" label="Expires" :value="old('expiresAt', optional($product?->expiresAt)->format('Y-m-d'))" placeholder="No expiry" />
    </div>
    <button type="submit">{{ $submit }}</button>
</form>
<script>
    (() => {
        const form = document.currentScript.previousElementSibling;
        if (!form || form.dataset.ready === '1') return;
        form.dataset.ready = '1';
        const name = form.querySelector('[data-product-name]');
        const sku = form.querySelector('[data-sku]');
        const photo = form.querySelector('[data-photo]');
        let locked = sku.hasAttribute('data-sku-lock') || sku.value.trim() !== '';
        let timer = null;
        let tried = false;
        const fillSku = async () => {
            const params = new URLSearchParams({ name: name.value, except: form.dataset.skuExcept || '' });
            const response = await fetch(form.dataset.skuUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            if (data.sku) sku.value = data.sku;
        };
        name.addEventListener('input', () => {
            if (locked) return;
            clearTimeout(timer);
            if (name.value.trim() === '') return;
            timer = setTimeout(fillSku, 350);
        });
        sku.addEventListener('input', () => { locked = true; });
        form.querySelector('[data-photo-open]').addEventListener('click', () => photo.click());
        photo.addEventListener('change', () => {
            const file = photo.files && photo.files[0];
            form.querySelector('[data-photo-name]').textContent = file ? file.name : 'Optional. JPG, PNG, or WebP, 500KB.';
        });
        form.addEventListener('submit', (event) => {
            if (tried || locked || sku.value.trim() !== '' || name.value.trim() === '') return;
            event.preventDefault();
            tried = true;
            fillSku().finally(() => form.requestSubmit());
        });
    })();
</script>
