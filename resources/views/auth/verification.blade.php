@extends('layouts.guest')

@section('title', 'Verify your account | SalesDock')

@section('content')
    <x-auth.shell title="Verify your account" lead="Enter your code to finish signing up.">
        @if (session('status'))
            <p role="status" class="text-sm text-green-700">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p role="alert" class="text-sm text-red-700">{{ $errors->first() }}</p>
        @endif
        @if ($pending?->verificationChannel === 'whatsapp')
            <p class="text-sm text-gray-500">You can still enter the WhatsApp code already sent to the number ending in {{ substr($pending->phone, -4) }}, or request a fresh code by email below.</p>
        @else
            <p class="text-sm text-gray-500">Check your inbox and spam folder. Come back here to enter your code.</p>
        @endif

        <form method="post" action="{{ route('register.verify-otp') }}" class="space-y-4" data-gate>
            @csrf
            @if ($pending)
                <input type="hidden" name="email" value="{{ $pending->email }}">
            @else
                <x-auth.field label="Email for this code" name="email" type="email" :value="old('email', $email)" required />
            @endif
            <label class="block text-center text-sm font-semibold" for="verification-code">6-digit verification code</label>
            <div class="verification-code">
                <div class="verification-code-cells" aria-hidden="true">
                    @for ($digit = 0; $digit < 6; $digit++)
                        <span></span>
                    @endfor
                </div>
                <input id="verification-code" name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required
                    class="w-full rounded-md border border-gray-400 p-3 text-center text-gray-900">
            </div>
            <x-auth.submit label="Verify code & continue" loading="Checking…" />
        </form>

        <form method="post" action="{{ route('register.resend') }}" class="space-y-4" data-gate>
            @csrf
            @if ($pending)
                <input type="hidden" name="email" value="{{ $pending->email }}">
            @else
                <p class="text-sm text-gray-500">On a different browser? Enter your signup details to resume and get a fresh code.</p>
                <x-auth.field label="Registered email" name="email" type="email" :value="old('email', $email)" required />
                <x-auth.field label="Signup password" name="password" type="password" autocomplete="current-password" />
            @endif
            <x-auth.submit label="Email a new code" loading="Sending…" />
        </form>
        <p class="text-sm"><a href="{{ route('login') }}" class="font-semibold text-green-600 underline">Back to login</a></p>
    </x-auth.shell>

    <style>
        .verification-code { position: relative; width: min(100%, 20rem); margin-inline: auto; }
        .verification-code-cells { display: none; }
        .verification-code[data-enhanced] .verification-code-cells {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: clamp(4px, 2vw, 8px);
        }
        .verification-code-cells span {
            display: grid;
            height: 3rem;
            place-items: center;
            border: 1px solid #9ca3af;
            border-radius: .5rem;
            color: #111827;
            font: 600 1.25rem/1 monospace;
        }
        .verification-code-cells span[data-filled] { border-color: #86efac; background: #f0fdf4; }
        .verification-code:focus-within span[data-active] {
            border-color: #15803d;
            box-shadow: 0 0 0 2px #15803d;
        }
        .verification-code[data-enhanced] input {
            position: absolute;
            inset: 0;
            height: 100%;
            opacity: 0;
            cursor: text;
        }
        @media (prefers-reduced-motion: no-preference) {
            .verification-code-cells span { transition: background-color 160ms ease, box-shadow 160ms ease; }
        }
    </style>
    <script>
        (() => {
            const input = document.getElementById('verification-code');
            const field = input.closest('.verification-code');
            const cells = [...field.querySelectorAll('.verification-code-cells span')];
            const motion = window.matchMedia('(prefers-reduced-motion: no-preference)');
            const render = (animate = false) => {
                const active = Math.min(input.selectionStart ?? input.value.length, cells.length - 1);
                cells.forEach((cell, index) => {
                    const digit = input.value[index] ?? '';
                    if (cell.textContent !== digit) {
                        cell.textContent = digit;
                        if (digit && animate && motion.matches) {
                            cell.animate([{ transform: 'scale(.93)' }, { transform: 'scale(1)' }], { duration: 150, easing: 'ease-out' });
                        }
                    }
                    cell.toggleAttribute('data-filled', digit !== '');
                    cell.toggleAttribute('data-active', index === active);
                });
            };
            input.addEventListener('input', () => {
                const digits = input.value.replace(/[^0-9]/g, '').slice(0, cells.length);
                if (input.value !== digits) {
                    const cursor = (input.selectionStart ?? input.value.length);
                    const digitsBeforeCursor = input.value.slice(0, cursor).replace(/[^0-9]/g, '').length;
                    input.value = digits;
                    input.setSelectionRange(digitsBeforeCursor, digitsBeforeCursor);
                }
                render(true);
            });
            input.addEventListener('paste', (event) => {
                if (!event.clipboardData) return;
                event.preventDefault();
                const digits = event.clipboardData.getData('text').replace(/[^0-9]/g, '');
                if (digits.length >= cells.length) {
                    input.value = digits.slice(0, cells.length);
                } else if (digits) {
                    input.setRangeText(digits, input.selectionStart, input.selectionEnd, 'end');
                }
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
            input.addEventListener('change', () => render());
            input.addEventListener('focus', () => render());
            input.addEventListener('click', () => render());
            input.addEventListener('keyup', () => render());
            window.addEventListener('pageshow', () => render());
            render();
            field.setAttribute('data-enhanced', '');
        })();
    </script>
@endsection
