@extends('layouts.guest')

@section('title', 'Register | SalesDock')

@section('content')
    @if (session('sent'))
        <x-auth.shell title="Check your email" :lead="'We sent a verification link to '.session('sent').'.'">
            <p class="text-sm text-gray-500">Click the link in your inbox to continue registration.</p>
            <a href="{{ route('login') }}" class="block w-full rounded-md bg-green-500 p-3 text-center font-semibold text-white">Back to login</a>
        </x-auth.shell>
    @else
        <x-auth.shell title="Register" lead="Create your account in a few steps.">
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="mb-6 flex items-center justify-center gap-6 text-xs font-semibold text-gray-500" id="step-dots">
                @foreach ([1 => 'Account', 2 => 'Business', 3 => 'Contact'] as $number => $label)
                    <div data-dot="{{ $number }}" class="flex flex-col items-center gap-1">
                        <span class="dot flex h-6 w-6 items-center justify-center rounded-full border border-gray-300 text-xs leading-none">{{ $number }}</span>
                        <span>{{ $label }}</span>
                    </div>
                @endforeach
            </div>

            <form method="post" action="{{ route('register') }}" id="register-form" class="space-y-5" autocomplete="off">
                @csrf
                <section data-step="1" class="space-y-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-auth.field label="Owner name" name="ownerName" :value="old('ownerName')" placeholder="John Doe" required />
                        <x-auth.field label="Business name" name="businessName" :value="old('businessName')" placeholder="Kemi's Fashion" required />
                    </div>
                    <x-auth.field label="Email" name="email" type="text" :value="old('email')" placeholder="you@business.com" inputmode="email" required />
                    <x-auth.password label="Password" name="password" placeholder="Min. 8 characters" minlength="8" required />
                </section>

                <section data-step="2" class="hidden space-y-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="relative w-full rounded-md border border-gray-400 p-3">
                            <p class="absolute left-3 top-0 -translate-y-1/2 bg-white px-2 text-sm text-gray-500">Business type <span class="text-red-500">*</span></p>
                            <select name="businessType" required class="w-full bg-transparent text-sm text-gray-900 outline-none">
                                <option value="">Choose</option>
                                @foreach ($businessTypes as $type)
                                    <option value="{{ $type }}" @selected(old('businessType') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-auth.field label="Business website" name="website" :value="old('website')" placeholder="www.yourbusiness.com" />
                    </div>
                    <x-auth.field label="Business address" name="address" :value="old('address')" placeholder="123 Main Street" required />
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-auth.field label="City" name="city" :value="old('city')" placeholder="Lagos" required />
                        <x-auth.field label="Phone" name="phone" :value="old('phone')" placeholder="+234..." required />
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-auth.field label="TIN No. (Optional)" name="tin" :value="old('tin')" placeholder="Tax ID" />
                        <x-auth.field label="RC Number (Optional)" name="rcNumber" :value="old('rcNumber')" placeholder="RC123456" />
                    </div>
                </section>

                <section data-step="3" class="hidden space-y-5">
                    <div class="relative w-full rounded-md border border-gray-400 p-3">
                        <p class="absolute left-3 top-0 -translate-y-1/2 bg-white px-2 text-sm text-gray-500">Message (optional)</p>
                        <textarea name="message" rows="3" placeholder="Anything you'd like us to know…" autocomplete="off" readonly data-no-fill class="w-full resize-none bg-transparent text-sm outline-none placeholder:text-gray-400">{{ old('message') }}</textarea>
                    </div>
                    <p class="text-xs text-gray-500">By submitting, you agree to our terms and understand we may contact you about your application.</p>
                </section>

                <div class="mt-6 flex items-center justify-between gap-4">
                    <button type="button" id="reg-back" class="text-sm text-gray-500 hover:text-gray-700">← Back to login</button>
                    <button type="button" id="reg-next" disabled class="rounded-md bg-green-500 px-6 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">Next</button>
                    <x-auth.submit id="reg-submit" class="hidden !w-auto px-6 py-2.5 text-sm" label="Submit registration" loading="Submitting…" />
                </div>
            </form>

            <p class="mt-2 text-sm text-gray-500">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-blue-500">Sign in</a></p>

            <script>
                const form = document.getElementById('register-form');
                const next = document.getElementById('reg-next');
                const submit = document.getElementById('reg-submit');
                const back = document.getElementById('reg-back');
                let step = 1;
                const value = (name) => (form.querySelector('[name="' + name + '"]')?.value || '').trim();
                const stepReady = (number) => {
                    if (number === 1) return value('ownerName') && value('businessName') && value('email') && value('password').length >= 8;
                    if (number === 2) return value('businessType') && value('address') && value('city') && value('phone');
                    return stepReady(1) && stepReady(2);
                };
                function paint() {
                    form.querySelectorAll('[data-step]').forEach((panel) => {
                        panel.classList.toggle('hidden', Number(panel.dataset.step) !== step);
                    });
                    document.querySelectorAll('[data-dot]').forEach((dot) => {
                        const on = Number(dot.dataset.dot) === step;
                        dot.classList.toggle('text-green-600', on);
                        const circle = dot.querySelector('.dot');
                        circle.classList.toggle('border-green-600', on);
                        circle.classList.toggle('bg-green-50', on);
                        circle.classList.toggle('text-green-700', on);
                        circle.classList.toggle('border-gray-300', !on);
                    });
                    back.textContent = step === 1 ? '← Back to login' : '← Back';
                    next.classList.toggle('hidden', step === 3);
                    submit.classList.toggle('hidden', step !== 3);
                    next.disabled = !stepReady(step);
                    submit.disabled = !stepReady(3);
                }
                form.addEventListener('input', paint);
                next.addEventListener('click', () => {
                    if (!stepReady(step) || step >= 3) return;
                    step += 1;
                    paint();
                });
                back.addEventListener('click', () => {
                    if (step === 1) window.location = @json(route('login'));
                    else { step -= 1; paint(); }
                });
                form.addEventListener('submit', (event) => {
                    if (step !== 3 || !stepReady(3)) event.preventDefault();
                });
                paint();
            </script>
        </x-auth.shell>
    @endif
@endsection
