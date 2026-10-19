@extends('layouts.guest')

@section('title', 'Register | SalesDock')

@section('content')
    @if (session('sent'))
        <x-auth.shell title="Check your email & WhatsApp" :lead="'We sent a verification link to '.session('sent').(session('sent_phone') ? ' and a 6-digit code to WhatsApp.' : '.')">
            <p class="text-sm text-gray-500">Open the email link, or enter the WhatsApp code below to continue and set your password.</p>
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('register.verify-otp') }}" class="space-y-4" autocomplete="one-time-code">
                @csrf
                <input type="hidden" name="email" value="{{ old('email', session('sent')) }}">
                <x-auth.field label="WhatsApp code" name="otp" :value="old('otp')" placeholder="123456" inputmode="numeric" maxlength="6" required />
                <x-auth.submit label="Continue with code" loading="Checking…" />
            </form>
            <form method="post" action="{{ route('register.resend') }}" class="pt-2">
                @csrf
                <input type="hidden" name="email" value="{{ old('email', session('sent')) }}">
                <button type="submit" class="text-sm font-semibold text-green-600">Resend email &amp; WhatsApp code</button>
            </form>
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
                <x-auth.field label="WhatsApp number" name="phone" type="tel" :value="old('phone')" placeholder="0803 000 0000" inputmode="tel" required />
                <x-turnstile action="signup" />
                <p class="text-xs text-gray-500">We'll email you a verification link and send a code to WhatsApp so you can set your password. By signing up, you agree to our <a href="{{ route('terms') }}" target="_blank" class="font-semibold text-green-600 underline">Terms</a> and <a href="{{ route('privacy') }}" target="_blank" class="font-semibold text-green-600 underline">Privacy Policy</a>.</p>
                <x-auth.submit id="reg-submit" label="Create account" loading="Creating account…" />
            </form>

            <p class="mt-2 text-sm text-gray-500">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-blue-500">Sign in</a></p>
        </x-auth.shell>
    @endif
@endsection
