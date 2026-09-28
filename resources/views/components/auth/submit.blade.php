@props(['label', 'loading' => 'Please wait…'])

<button
    type="submit"
    data-loading="{{ $loading }}"
    {{ $attributes->merge(['class' => 'w-full rounded-md bg-green-500 p-3 font-semibold text-gray-900 disabled:cursor-not-allowed disabled:opacity-60']) }}
>{{ $label }}</button>
