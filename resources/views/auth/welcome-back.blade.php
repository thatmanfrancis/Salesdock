@extends('layouts.guest')

@section('title', 'Welcome back | SalesDock')

@section('content')
    <x-auth.shell title="Welcome back" :lead="'Hi '.$name.', you have not used SalesDock for '.$days.' days.'">
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <p class="text-sm text-gray-600">Is anything wrong? If something kept you away, tell us and we will look into it.</p>

        <form method="post" action="{{ route('welcome-back.store') }}" class="space-y-5">
            @csrf
            <div class="relative w-full rounded-md border border-gray-400 p-3">
                <p class="absolute left-3 top-0 -translate-y-1/2 bg-white px-2 text-sm text-gray-500">Reason (optional)</p>
                <textarea name="reason" rows="4" maxlength="1000" placeholder="Tell us what happened…" class="w-full resize-none bg-transparent text-sm outline-none placeholder:text-gray-400">{{ old('reason') }}</textarea>
            </div>
            <x-auth.submit label="Continue" loading="Continuing…" />
        </form>
    </x-auth.shell>
@endsection
