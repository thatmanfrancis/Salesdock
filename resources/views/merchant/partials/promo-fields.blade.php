@php
    $picked = old('form') === $form;
    $type = $picked ? old('discountType', 'PERCENTAGE') : 'PERCENTAGE';
    $productId = $picked ? (string) old('productId', '') : '';
    $match = $productId !== '' ? $catalog->firstWhere('id', $productId) : null;
    $start = $picked ? (string) old('startDate', '') : '';
    $end = $picked ? (string) old('endDate', '') : '';
@endphp
<div class="ui-modal-grid">
<label class="wide">
    <span>Name <span class="req">*</span></span>
    <input name="name" value="{{ $picked ? old('name') : '' }}" placeholder="Weekend sale" required data-field="name">
</label>
<x-ui.select name="discountType" label="Type" :required="true" :value="$type" :options="['PERCENTAGE' => 'Percentage', 'FIXED' => 'Fixed amount']" />
<label>
    <span>Value <span class="req">*</span></span>
    <input type="number" name="discountValue" min="0.01" step="0.01" value="{{ $picked ? old('discountValue') : '' }}" placeholder="{{ $type === 'FIXED' ? '0.00' : '10' }}" required data-field="value">
</label>
<div class="wide product-pick" data-product-pick>
    <input type="hidden" name="productId" value="{{ $productId }}">
    <span>Product</span>
    <div class="product-chip" data-product-chip @if (! $match) hidden @endif>
        <span data-product-name>{{ $match->name ?? '' }}</span>
        <button type="button" data-product-clear aria-label="Clear product">✕</button>
    </div>
    <input type="search" data-product-search placeholder="Search product name or SKU" @if ($match) hidden @endif>
    <ul class="product-hits" data-product-hits hidden></ul>
</div>
<div class="wide ui-filter-dates">
    <x-ui.date name="startDate" label="Start" :required="true" :value="$start" placeholder="Choose a day" />
    <x-ui.date name="endDate" label="End" :required="true" :value="$end" placeholder="Choose a day" />
</div>
</div>
