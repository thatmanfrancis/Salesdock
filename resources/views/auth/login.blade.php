@extends('layouts.guest')

@section('title', 'Log in | SalesDock')

@section('content')
    <x-auth.shell title="Log in" lead="Welcome back! Please enter your details.">
        @if (session('status'))
            <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        @if (session('pin_change_user'))
            <form method="post" action="{{ route('login') }}" class="space-y-5" autocomplete="off" data-gate>
                @csrf
                <input type="hidden" name="method" value="pin">
                <x-auth.password label="New PIN" name="new_pin" placeholder="Enter new PIN" minlength="4" maxlength="6" required autofocus />
                <x-auth.password label="Confirm PIN" name="new_pin_confirmation" placeholder="Confirm new PIN" minlength="4" maxlength="6" required />
                <x-auth.submit label="Set PIN & Continue" loading="Saving..." disabled />
            </form>
        @elseif (session('pending_2fa'))
            <form method="post" action="{{ route('login') }}" class="space-y-6" autocomplete="off" data-gate>
                @csrf
                <input type="hidden" name="email" value="{{ old('email') }}">
                <div class="text-center">
                    <p class="mb-4 text-sm text-gray-500">Enter the 6-digit code from your authenticator app.</p>
                    <div class="flex justify-between gap-2" id="otp">
                        @for ($i = 0; $i < 6; $i++)
                            <input name="otp[]" inputmode="numeric" maxlength="1" required autocomplete="off" readonly data-no-fill class="h-14 w-12 rounded-lg border-2 border-gray-300 bg-white text-center text-2xl font-bold outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20" @if ($i === 0) autofocus @endif>
                        @endfor
                    </div>
                </div>
                <x-auth.submit label="Verify code" loading="Verifying..." disabled />
                <a href="{{ route('login', ['fresh' => 1]) }}" class="flex w-full items-center justify-center rounded-md border border-gray-300 p-3 hover:bg-gray-50">Back to login</a>
            </form>
            <script>
                const boxes = document.querySelectorAll('#otp input');
                boxes.forEach((box, index) => {
                    box.addEventListener('input', () => {
                        box.value = box.value.replace(/\D/g, '').slice(-1);
                        if (box.value && boxes[index + 1]) boxes[index + 1].focus();
                    });
                    box.addEventListener('keydown', (event) => {
                        if (event.key === 'Backspace' && !box.value && boxes[index - 1]) boxes[index - 1].focus();
                    });
                });
                document.getElementById('otp').addEventListener('paste', (event) => {
                    const text = (event.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                    if (text.length < 6) return;
                    event.preventDefault();
                    [...text].forEach((digit, index) => { boxes[index].value = digit; });
                            boxes[5].focus();
                            document.getElementById('otp').dispatchEvent(new Event('input', { bubbles: true }));
                        });
            </script>
        @else
            <form id="login-form" method="post" action="{{ route('login') }}" class="space-y-5" autocomplete="off" data-gate>
                @csrf
                <input type="text" name="fake-user" class="hidden" tabindex="-1" autocomplete="off">
                <input type="password" name="fake-pass" class="hidden" tabindex="-1" autocomplete="off">
                <input type="hidden" name="slug" value="{{ old('slug', request('slug')) }}">
                <div class="grid grid-cols-2 gap-2 rounded-md border border-gray-200 p-1">
                    <label>
                        <input type="radio" name="method" value="password" class="peer sr-only" @checked(old('method', 'password') !== 'pin')>
                        <span class="block cursor-pointer rounded-md px-3 py-2 text-center text-sm font-semibold text-gray-600 peer-checked:bg-green-500 peer-checked:text-gray-900">Email Login</span>
                    </label>
                    <label>
                        <input type="radio" name="method" value="pin" class="peer sr-only" @checked(old('method') === 'pin')>
                        <span class="block cursor-pointer rounded-md px-3 py-2 text-center text-sm font-semibold text-gray-600 hover:bg-gray-50 peer-checked:bg-green-500 peer-checked:text-gray-900">PIN Login</span>
                    </label>
                </div>

                <div class="password-panel space-y-5">
                    <x-auth.field label="Email" name="email" type="text" :value="old('email')" placeholder="example@gmail.com" inputmode="email" required />
                    <x-auth.password label="Password" name="password" placeholder="Enter password" required />
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="remember" class="h-4 w-4" checked>
                            <p class="text-sm text-gray-500">Remember me</p>
                        </label>
                        <a href="{{ route('password.request') }}" class="text-sm font-semibold text-gray-500">Forgot password?</a>
                    </div>
                    <x-auth.submit label="Login" loading="Logging in..." disabled />
                </div>

                <div class="pin-panel hidden space-y-5">
                    <x-auth.password label="PIN" name="pin" placeholder="Enter your PIN" minlength="4" maxlength="6" inputmode="numeric" required />
                    <x-auth.submit label="Login with PIN" loading="Logging in..." disabled />
                </div>
            </form>
            <script>
                const form = document.getElementById('login-form');
                const passwordPanel = form.querySelector('.password-panel');
                const pinPanel = form.querySelector('.pin-panel');
                function showTab() {
                    const pin = form.querySelector('[name="method"][value="pin"]').checked;
                    passwordPanel.classList.toggle('hidden', pin);
                    pinPanel.classList.toggle('hidden', !pin);
                    passwordPanel.querySelectorAll('input, button').forEach((input) => { input.disabled = pin; });
                    pinPanel.querySelectorAll('input, button').forEach((input) => { input.disabled = !pin; });
                    window.salesdockGate?.(form);
                }
                form.querySelectorAll('[name="method"]').forEach((input) => input.addEventListener('change', showTab));
                showTab();
            </script>

            <div>You don't have an account? <a href="{{ route('register') }}" class="font-semibold text-blue-500">Sign up</a></div>
        @endif
    </x-auth.shell>
@endsection
