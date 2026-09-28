@extends('layouts.guest')

@section('title', 'Choose your plan | SalesDock')

@section('content')
    <div class="flex min-h-screen items-center justify-center bg-linear-to-br from-gray-50 via-white to-green-50/30 p-4 py-12">
        <div class="w-full max-w-5xl">
            <div class="mb-10 text-center">
                <x-auth.logo />
                <p class="mb-2 text-xs font-bold uppercase tracking-widest text-green-600">SalesDock Onboarding</p>
                <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">Choose your plan</h1>
                <p class="mt-2 text-sm text-gray-500">You can upgrade or downgrade anytime. All prices are in Nigerian Naira (₦).</p>
                <p class="mt-2 text-xs text-gray-400">After you continue, you have 30 minutes to complete payment. A failed or cancelled attempt does not expire the link — try again from this page.</p>
            </div>

            @if ($errors->any())
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-center text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            @if ($registration)
                <form method="post" action="{{ route('select-plan.store') }}" autocomplete="off" data-gate>
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="mb-10 flex justify-center gap-2">
                        @foreach (['MONTHLY' => 'Monthly', 'QUARTERLY' => 'Quarterly', 'ANNUALLY' => 'Annually'] as $cycle => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="billingCycle" value="{{ $cycle }}" class="peer sr-only" @checked($cycle === 'MONTHLY')>
                                <span class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 peer-checked:border-gray-900 peer-checked:bg-gray-900 peer-checked:text-white">
                                    {{ $label }}
                                    @if ($cycle === 'QUARTERLY')<b class="ml-1.5 rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-bold text-green-700 peer-checked:bg-green-500 peer-checked:text-white">10% off</b>@endif
                                    @if ($cycle === 'ANNUALLY')<b class="ml-1.5 rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-bold text-green-700">20% off</b>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mb-10 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                        @forelse ($plans as $plan)
                            @php
                                $popular = str_contains(strtolower($plan->name), 'professional');
                                $money = fn ($amount) => (float) $amount <= 0 ? 'Free' : '₦'.number_format($amount);
                                $limit = function ($value, $one, $many, $unlimited, $cap = 999) {
                                    if ($value === null || $value < 0 || $value >= $cap) return $unlimited;
                                    return 'Up to '.$value.' '.($value === 1 ? $one : $many);
                                };
                            @endphp
                            <label class="relative cursor-pointer rounded-3xl border-2 border-gray-200 bg-white p-6 has-[:checked]:scale-[1.02] has-[:checked]:border-green-500 has-[:checked]:shadow-xl {{ $popular ? 'ring-1 ring-green-300' : '' }}">
                                @if ($popular)
                                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-green-500 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-white">Most Popular</span>
                                @endif
                                <input type="radio" name="planId" value="{{ $plan->id }}" class="sr-only" required data-monthly="{{ $plan->monthlyPrice }}" data-quarterly="{{ $plan->quarterlyPrice }}" data-annual="{{ $plan->annualPrice }}">
                                <h3 class="mb-1 text-lg font-extrabold text-gray-900">{{ $plan->name }}</h3>
                                <p class="mb-5 text-xs leading-relaxed text-gray-500">{{ $plan->description }}</p>
                                <p class="price monthly text-4xl font-black tracking-tight">{{ $money($plan->monthlyPrice) }}</p>
                                <p class="price quarterly hidden text-4xl font-black tracking-tight">{{ $money($plan->quarterlyPrice) }}</p>
                                <p class="price annual hidden text-4xl font-black tracking-tight">{{ $money($plan->annualPrice) }}</p>
                                <p class="per monthly mb-5 text-xs text-gray-400">{{ (float) $plan->monthlyPrice <= 0 ? 'no payment required' : 'per mo' }}</p>
                                <p class="per quarterly mb-5 hidden text-xs text-gray-400">{{ (float) $plan->quarterlyPrice <= 0 ? 'no payment required' : 'per qtr' }}</p>
                                <p class="per annual mb-5 hidden text-xs text-gray-400">{{ (float) $plan->annualPrice <= 0 ? 'no payment required' : 'per yr' }}</p>
                                <ul class="space-y-2.5 text-sm text-gray-600">
                                    <li>{{ $limit($plan->maxBranches, 'branch', 'branches', 'Unlimited branches') }}</li>
                                    <li>{{ $limit($plan->maxUsers, 'staff', 'staff', 'Unlimited staff') }}</li>
                                    <li>{{ $limit($plan->maxProducts, 'product', 'products', 'Unlimited products', 999999) }}</li>
                                </ul>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500">No active plans are available.</p>
                        @endforelse
                    </div>
                    <div class="text-center">
                        <button type="submit" data-plan-submit data-loading="Please wait…" disabled class="rounded-2xl bg-green-500 px-10 py-4 text-base font-bold text-white shadow-lg shadow-green-200 hover:bg-green-600 disabled:cursor-not-allowed disabled:opacity-60">Select a plan to continue</button>
                        <p data-pay-note class="mt-3 hidden text-xs text-gray-400">Secured by Flutterwave · Bank Transfer, Card &amp; USSD supported</p>
                    </div>
                </form>
                <script>
                    const form = document.querySelector('form');
                    const button = form.querySelector('[data-plan-submit]');
                    const payNote = form.querySelector('[data-pay-note]');
                    const cycleKey = { MONTHLY: 'monthly', QUARTERLY: 'quarterly', ANNUALLY: 'annual' };
                    function showCycle() {
                        const cycle = form.querySelector('[name="billingCycle"]:checked').value;
                        form.querySelectorAll('.price, .per').forEach((node) => node.classList.add('hidden'));
                        form.querySelectorAll('.' + cycleKey[cycle]).forEach((node) => node.classList.remove('hidden'));
                        const selected = form.querySelector('[name="planId"]:checked');
                        if (!selected) {
                            button.textContent = 'Select a plan to continue';
                            button.dataset.loading = 'Please wait…';
                            payNote.classList.add('hidden');
                            return;
                        }
                        const amount = Number(selected.dataset[cycleKey[cycle]] || 0);
                        if (amount <= 0) {
                            button.textContent = 'Continue';
                            button.dataset.loading = 'Continuing…';
                            payNote.classList.add('hidden');
                            return;
                        }
                        button.textContent = 'Continue with payment';
                        button.dataset.loading = 'Opening payment…';
                        payNote.classList.remove('hidden');
                    }
                    form.querySelectorAll('[name="billingCycle"], [name="planId"]').forEach((input) => input.addEventListener('change', showCycle));
                    showCycle();
                </script>
            @else
                <p class="text-center text-sm text-gray-500">This plan link is invalid or expired.</p>
            @endif
        </div>
    </div>
@endsection
