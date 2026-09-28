@props([
    'label',
    'name',
    'type' => 'text',
    'value' => '',
    'placeholder' => '',
    'required' => false,
])

<div class="relative w-full rounded-md border border-gray-400 p-3">
    <p class="absolute left-3 top-0 -translate-y-1/2 bg-white px-2 text-sm text-gray-500">
        {{ $label }}
        @if ($required)<span class="text-red-500">*</span>@endif
    </p>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        @if ($required) required @endif
        autocomplete="off"
        autocorrect="off"
        autocapitalize="none"
        spellcheck="false"
        readonly
        data-no-fill
        {{ $attributes->merge(['class' => 'h-full w-full bg-transparent text-sm text-gray-900 outline-none placeholder:text-gray-400']) }}
    >
</div>
