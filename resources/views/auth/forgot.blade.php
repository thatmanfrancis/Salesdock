@extends('layouts.guest')

@section('title', 'Forgot password | SalesDock')

@section('content')
    <x-auth.shell title="Forgot password?" lead="We'll send a reset link to your email">
        @if (session('status'))
            <p class="font-semibold text-gray-900">Check your email</p>
            <p class="text-sm text-gray-500">If an account exists for <span class="font-medium text-gray-700">{{ old('email') }}</span>, a reset link has been sent.</p>
            <a href="{{ route('login') }}" class="text-sm font-medium text-green-600">← Back to login</a>
        @else
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('password.email') }}" class="space-y-5" autocomplete="off" data-gate>
                @csrf
                <x-auth.field label="Email" name="email" type="text" :value="old('email')" placeholder="you@business.com" inputmode="email" required />
                <x-auth.submit label="Send Reset Link" loading="Sending…" disabled />
            </form>
            <a href="{{ route('login') }}" class="text-sm font-medium text-green-600">← Back to login</a>
        @endif
    </x-auth.shell>
@endsection
