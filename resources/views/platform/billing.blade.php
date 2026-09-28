@extends('layouts.app')

@section('title', 'Billing | SalesDock')

@php
    $cycles = [
        'MONTHLY' => 'Monthly',
        'QUARTERLY' => 'Quarterly',
        'ANNUALLY' => 'Annually',
    ];
    $labels = [
        'PAID' => 'Paid',
        'PENDING' => 'Pending',
        'FAILED' => 'Failed',
        'REFUNDED' => 'Refunded',
        'WAIVED' => 'Waived',
    ];
    $tone = [
        'PAID' => 'active',
        'PENDING' => 'pending',
        'FAILED' => 'overdue',
        'REFUNDED' => 'cancelled',
        'WAIVED' => 'draft',
    ];
    $tiers = [
        'STARTER' => 'Starter',
        'PROFESSIONAL' => 'Professional',
        'ENTERPRISE' => 'Enterprise',
    ];
    $slot = fn ($count) => (int) $count >= 999 ? 'Unlimited' : number_format((int) $count);
    $naira = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $day = fn ($date) => $date?->timezone('Africa/Lagos')->format('d M Y') ?: '—';
    $lines = function ($plan) {
        $raw = $plan->features;

        return is_array($raw) ? array_values(array_filter($raw, fn ($item) => is_string($item) && trim($item) !== '')) : [];
    };
    $creating = old('form') === 'create' && $errors->any();
@endphp

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Billing</h1>
                <span class="tally">{{ number_format($total) }} total</span>
            </div>
            <x-ui.filter :action="route('admin.billing')" :active="$filtered" label="Filter invoices">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Shop name" maxlength="80">
                </div>
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'PAID' => 'Paid', 'PENDING' => 'Pending', 'FAILED' => 'Failed', 'REFUNDED' => 'Refunded', 'WAIVED' => 'Waived']" />
                <x-ui.select name="cycle" label="Cycle" :value="$cycle" :options="['' => 'All', 'MONTHLY' => 'Monthly', 'QUARTERLY' => 'Quarterly', 'ANNUALLY' => 'Annually']" />
            </x-ui.filter>
        </div>

        <div class="stats quad">
            @foreach ($cards as $card)
                <article>
                    <span>{{ $card['label'] }}</span>
                    <strong>{{ $card['value'] }}</strong>
                </article>
            @endforeach
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Shop</th>
                        <th>Invoice</th>
                        <th>Amount</th>
                        <th>Cycle</th>
                        <th>Status</th>
                        <th>Period</th>
                        <th>Paid</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td>
                                @if ($invoice->tenant)
                                    <a href="{{ route('admin.tenants.show', $invoice->tenant) }}">{{ $invoice->tenant->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $invoice->invoiceNumber }}</td>
                            <td>{{ $naira($invoice->amount) }}</td>
                            <td>{{ $cycles[$invoice->billingCycle] ?? $invoice->billingCycle }}</td>
                            <td><span class="badge {{ $tone[$invoice->status] ?? 'draft' }}">{{ $labels[$invoice->status] ?? $invoice->status }}</span></td>
                            <td>{{ $day($invoice->periodStart) }} to {{ $day($invoice->periodEnd) }}</td>
                            <td>{{ $day($invoice->paidAt) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="7">No invoices.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <nav class="pager" aria-label="Pages">
                @if ($page > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">Previous</a>
                @else
                    <span class="off">Previous</span>
                @endif
                <span>Page {{ $page }} of {{ $pages }}</span>
                @if ($page < $pages)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Next</a>
                @else
                    <span class="off">Next</span>
                @endif
            </nav>
        </section>

        <div class="page-head" id="plans">
            <div class="heading">
                <h1>Plans</h1>
                <span class="tally">{{ number_format($plans->count()) }} total</span>
            </div>
            <button type="button" data-plan-open="create-plan">New plan</button>
        </div>

        @if ($plans->isEmpty())
            <section class="card">
                <p class="empty">No plans.</p>
            </section>
        @else
            <div class="plan-grid platform">
                @foreach ($plans as $plan)
                    @php
                        $owned = $lines($plan);
                        $choices = array_values(array_unique([...$catalogue, ...$owned]));
                        $editing = old('form') === 'edit' && old('plan') === $plan->id && $errors->any();
                        $picked = $editing ? (array) old('features', []) : $owned;
                    @endphp
                    <article class="plan-card platform">
                        <div class="plan-top">
                            <div>
                                <p class="tier">{{ $tiers[$plan->tier] ?? $plan->tier }}</p>
                                <h2>{{ $plan->name }}</h2>
                                <span class="badge {{ $plan->isActive ? 'active' : 'cancelled' }}">{{ $plan->isActive ? 'Active' : 'Inactive' }}</span>
                            </div>
                            <p class="price">{{ $naira($plan->monthlyPrice) }} <span>/mo</span></p>
                        </div>
                        <p>{{ $plan->description ?: 'No description.' }}</p>
                        <p class="limits">{{ $slot($plan->maxBranches) }} {{ (int) $plan->maxBranches === 1 ? 'branch' : 'branches' }}, {{ $slot($plan->maxUsers) }} users, {{ $slot($plan->maxProducts) }} products</p>
                        <details class="plan-fold">
                            <summary>Billing options</summary>
                            <div class="fold-body">
                                <div><span>Monthly</span><strong>{{ $naira($plan->monthlyPrice) }}</strong></div>
                                <div><span>Quarterly</span><strong>{{ $naira($plan->quarterlyPrice) }}</strong></div>
                                <div><span>Annual</span><strong>{{ $naira($plan->annualPrice) }}</strong></div>
                            </div>
                        </details>
                        <details class="plan-fold">
                            <summary>Features ({{ count($owned) }})</summary>
                            <div class="fold-body">
                                @if ($owned === [])
                                    <p>No features.</p>
                                @else
                                    @foreach ($owned as $feature)
                                        <span class="pill">{{ $feature }}</span>
                                    @endforeach
                                @endif
                            </div>
                        </details>
                        <div class="row-actions">
                            <button type="button" data-plan-open="plan-{{ $plan->id }}">Edit</button>
                            @if ($plan->isActive)
                                <button type="button" class="quiet" data-ask data-url="{{ route('admin.plans.active', $plan) }}" data-copy="Turn off {{ $plan->name }}? New shops will not see this plan.">Deactivate</button>
                            @else
                                <form method="post" action="{{ route('admin.plans.active', $plan) }}">
                                    @csrf
                                    <input type="hidden" name="active" value="1">
                                    <button type="submit">Activate</button>
                                </form>
                            @endif
                        </div>
                    </article>

                    <div class="ui-modal" id="plan-{{ $plan->id }}" @unless ($editing) hidden @endunless>
                        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
                        <form class="ui-modal-sheet tall" method="post" action="{{ route('admin.plans.update', $plan) }}" data-busy>
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="form" value="edit">
                            <input type="hidden" name="plan" value="{{ $plan->id }}">
                            <div class="ui-filter-head">
                                <strong>Edit {{ $plan->name }}</strong>
                                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
                            </div>
                            @include('platform.plan-fields', [
                                'name' => $editing ? old('name', $plan->name) : $plan->name,
                                'tier' => $editing ? old('tier', $plan->tier) : $plan->tier,
                                'description' => $editing ? old('description', $plan->description) : $plan->description,
                                'monthly' => $editing ? old('monthlyPrice', $plan->monthlyPrice) : $plan->monthlyPrice,
                                'quarterly' => $editing ? old('quarterlyPrice', $plan->quarterlyPrice) : $plan->quarterlyPrice,
                                'annual' => $editing ? old('annualPrice', $plan->annualPrice) : $plan->annualPrice,
                                'branches' => $editing ? old('maxBranches', $plan->maxBranches) : $plan->maxBranches,
                                'users' => $editing ? old('maxUsers', $plan->maxUsers) : $plan->maxUsers,
                                'products' => $editing ? old('maxProducts', $plan->maxProducts) : $plan->maxProducts,
                                'choices' => $choices,
                                'picked' => $picked,
                                'tiers' => $tiers,
                            ])
                            <div class="ui-modal-actions">
                                <button type="button" data-close>Cancel</button>
                                <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="ui-modal" id="create-plan" @unless ($creating) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet tall" method="post" action="{{ route('admin.plans.store') }}" data-busy>
            @csrf
            <input type="hidden" name="form" value="create">
            <div class="ui-filter-head">
                <strong>New plan</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            @include('platform.plan-fields', [
                'name' => $creating ? old('name') : '',
                'tier' => $creating ? old('tier', 'STARTER') : 'STARTER',
                'description' => $creating ? old('description') : '',
                'monthly' => $creating ? old('monthlyPrice', 0) : 0,
                'quarterly' => $creating ? old('quarterlyPrice', 0) : 0,
                'annual' => $creating ? old('annualPrice', 0) : 0,
                'branches' => $creating ? old('maxBranches', 1) : 1,
                'users' => $creating ? old('maxUsers', 5) : 5,
                'products' => $creating ? old('maxProducts', 500) : 500,
                'choices' => $catalogue,
                'picked' => $creating ? (array) old('features', []) : [],
                'tiers' => $tiers,
            ])
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Adding…"><span data-label>Add plan</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-ask-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('admin.billing') }}" data-busy>
            @csrf
            <input type="hidden" name="active" value="0">
            <div class="ui-filter-head">
                <strong>Turn off plan</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask" data-ask-copy></p>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" class="danger" data-loading="Turning off…"><span data-label>Turn off</span></button>
            </div>
        </form>
    </div>

    <script>
        const closeBox = (box) => {
            const busy = box.querySelector('button.is-busy');
            if (!busy) box.hidden = true;
        };
        document.querySelectorAll('[data-plan-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const box = document.getElementById(button.dataset.planOpen);
                if (box) box.hidden = false;
            });
        });
        document.querySelectorAll('.ui-modal [data-close]').forEach((button) => {
            button.addEventListener('click', () => closeBox(button.closest('.ui-modal')));
        });
        document.querySelectorAll('[data-monthly]').forEach((input) => {
            input.addEventListener('input', () => {
                const form = input.closest('form');
                const monthly = Number(input.value) || 0;
                const quarterly = form.querySelector('[data-quarterly]');
                const annual = form.querySelector('[data-annual]');
                if (quarterly) quarterly.value = String(Math.round(monthly * 3 * 0.9));
                if (annual) annual.value = String(Math.round(monthly * 12 * 0.8));
            });
        });
        const askModal = document.querySelector('[data-ask-modal]');
        const askForm = askModal.querySelector('form');
        const askCopy = askModal.querySelector('[data-ask-copy]');
        const askSubmit = askForm.querySelector('[type="submit"]');
        document.querySelectorAll('[data-ask]').forEach((button) => {
            button.addEventListener('click', () => {
                askForm.action = button.dataset.url;
                askCopy.textContent = button.dataset.copy || '';
                askModal.hidden = false;
            });
        });
        document.querySelectorAll('form[data-busy]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('[type="submit"]');
                const label = button?.querySelector('[data-label]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                if (label) label.textContent = button.dataset.loading || 'Saving…';
            });
        });
    </script>
@endsection
