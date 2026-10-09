@extends('layouts.guest')

@section('title', 'Set your password | SalesDock')

@section('content')
    <x-auth.shell title="Verify your email" :lead="'Set a password for '.$businessName.' to finish verifying '.$email.'.'">
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ route('verify-email.store') }}" class="space-y-5" autocomplete="off" data-gate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-auth.password label="Password" name="password" placeholder="Min. 8 characters" minlength="8" required />
            <x-auth.password label="Confirm password" name="password_confirmation" placeholder="Repeat password" minlength="8" required />
            <x-auth.submit label="Verify & continue" loading="Verifying…" disabled />
        </form>
    </x-auth.shell>
@endsection
