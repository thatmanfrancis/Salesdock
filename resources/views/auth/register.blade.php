@extends('layouts.guest')

@section('title', 'Register | SalesDock')

@section('content')
    @if (session('sent'))
        <x-auth.shell title="Check your email" :lead="'We sent a verification link to '.session('sent').'.'">
            <p class="text-sm text-gray-500">Click the link in your inbox to verify your email and set your password.</p>
            <a href="{{ route('login') }}" class="block w-full rounded-md bg-green-500 p-3 text-center font-semibold text-white">Back to login</a>
        </x-auth.shell>
    @else
        <x-auth.shell title="Register" lead="Create your SalesDock account.">
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('register') }}" id="register-form" class="space-y-5" autocomplete="off" data-gate>
                @csrf
                <x-auth.field label="Business name" name="businessName" :value="old('businessName')" placeholder="Kemi's Fashion" required />
                <x-auth.field label="Email" name="email" type="text" :value="old('email')" placeholder="you@business.com" inputmode="email" required />
                <x-turnstile action="signup" />
                <p class="text-xs text-gray-500">We'll email you a link to verify this address and set your password. By signing up, you agree to our <a href="{{ route('terms') }}" target="_blank" class="font-semibold text-green-600 underline">Terms</a> and <a href="{{ route('privacy') }}" target="_blank" class="font-semibold text-green-600 underline">Privacy Policy</a>.</p>
                <x-auth.submit id="reg-submit" label="Create account" loading="Creating account…" />
            </form>

            <p class="mt-2 text-sm text-gray-500">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-blue-500">Sign in</a></p>
        </x-auth.shell>
    @endif
@endsection
