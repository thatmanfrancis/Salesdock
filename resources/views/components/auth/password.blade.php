@props([
    'label',
    'name',
    'value' => '',
    'placeholder' => '',
    'required' => false,
    'minlength' => null,
    'maxlength' => null,
])

<div class="relative w-full rounded-md border border-gray-400 p-3">
    <p class="absolute left-3 top-0 -translate-y-1/2 bg-white px-2 text-sm text-gray-500">
        {{ $label }}
        @if ($required)<span class="text-red-500">*</span>@endif
    </p>
    <input
        type="password"
        name="{{ $name }}"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        @if ($required) required @endif
        @if ($minlength) minlength="{{ $minlength }}" @endif
        @if ($maxlength) maxlength="{{ $maxlength }}" @endif
        autocomplete="off"
        autocorrect="off"
        autocapitalize="none"
        spellcheck="false"
        readonly
        data-no-fill
        {{ $attributes->merge(['class' => 'h-full w-full bg-transparent pr-8 text-sm text-gray-900 outline-none placeholder:text-gray-400']) }}
    >
    <button type="button" data-eye class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500" aria-label="Show password">
        <svg data-eye-open width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg data-eye-shut class="hidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M1 1l22 22"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
    </button>
</div>
