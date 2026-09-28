@extends('layouts.guest')

@section('title', 'Set new password | SalesDock')

@section('content')
    <x-auth.shell title="Set new password" lead="Choose a strong password for your account">
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ route('password.update') }}" class="space-y-5" autocomplete="off" data-gate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-auth.password label="New Password" name="password" placeholder="Min. 8 characters" minlength="8" required />
            <x-auth.password label="Confirm Password" name="password_confirmation" placeholder="Repeat password" minlength="8" required />
            <x-auth.submit label="Reset Password" loading="Saving…" disabled />
        </form>
        <a href="{{ route('login') }}" class="text-sm font-medium text-green-600">← Back to login</a>
    </x-auth.shell>
@endsection
