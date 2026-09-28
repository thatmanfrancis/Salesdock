@props(['title' => null, 'lead' => null, 'wide' => false])

<div class="flex min-h-screen flex-col items-center justify-center bg-white px-4">
    <div @class(['p-4', 'w-full max-w-5xl' => $wide, 'w-[500px] max-md:w-[90%]' => ! $wide])>
        <x-auth.logo />
        @if ($title)
            <h1 class="text-3xl font-bold tracking-wide">{{ $title }}</h1>
        @endif
        @if ($lead)
            <p class="font-semibold text-gray-500">{{ $lead }}</p>
        @endif
        <div class="my-8"></div>
        <div class="space-y-5">
            {{ $slot }}
        </div>
    </div>
</div>
