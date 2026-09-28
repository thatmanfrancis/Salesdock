@extends('layouts.app')

@section('title', 'Billing | SalesDock')

@php
    $money = function ($amount) {
        $amount = (float) $amount;
        $digits = abs($amount - round($amount)) < 0.001 ? 0 : 2;

        return '₦'.number_format($amount, $digits);
    };
    $price = function ($plan) use ($cycle, $money) {
        $amount = (float) match ($cycle) {
            'QUARTERLY' => $plan->quarterlyPrice,
            'ANNUALLY' => $plan->annualPrice,
            default => $plan->monthlyPrice,
        };

        return $amount <= 0 ? 'Free' : $money($amount);
    };
    $slot = fn ($count) => (int) $count >= 999 ? 'Unlimited' : number_format((int) $count);
    $features = function ($plan) {
        $raw = $plan->features;
        if (! is_array($raw)) {
            return [];
        }
        if (array_is_list($raw)) {
            return array_values(array_filter($raw, fn ($item) => is_string($item) && trim($item) !== ''));
        }
        $lines = [];
        foreach ($raw as $key => $value) {
            if ($value) {
                $lines[] = \Illuminate\Support\Str::headline((string) $key);
            }
        }

        return $lines;
    };
    $currentPrice = null;
    if ($subscription?->plan) {
        $amount = (float) match ($subscription->billingCycle) {
            'QUARTERLY' => $subscription->plan->quarterlyPrice,
            'ANNUALLY' => $subscription->plan->annualPrice,
            default => $subscription->plan->monthlyPrice,
        };
        $currentPrice = $amount <= 0 ? 'Free' : $money($amount);
    }
@endphp

@section('content')
    <div class="page">
        <section class="bill-now">
            @if ($subscription?->plan)
                <div>
                    <span>Current plan</span>
                    <strong>{{ $subscription->plan->name }}</strong>
                    <p>
                        <span class="badge {{ $subscription->status === 'ACTIVE' ? 'active' : ($subscription->status === 'PAST_DUE' ? 'pending' : 'cancelled') }}">{{ $statuses[$subscription->status] ?? $subscription->status }}</span>
                        {{ $cycles[$subscription->billingCycle] ?? $subscription->billingCycle }} at {{ $currentPrice }}.
                        Period ends {{ $subscription->currentPeriodEnd?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}.
                    </p>
                </div>
            @else
                <div>
                    <span>Current plan</span>
                    <strong>No subscription on this shop.</strong>
                </div>
            @endif
        </section>

        <div class="cycle-pick">
            @foreach ($cycles as $key => $label)
                <a href="{{ route('billing', ['cycle' => $key]) }}" @class(['on' => $cycle === $key])>{{ $label }}</a>
            @endforeach
            <span>{{ $hints[$cycle] }}</span>
        </div>

        @unless ($canChange)
            <p class="bill-note">Ask the owner or a manager to change the plan.</p>
        @endunless

        <div class="plan-grid">
            @foreach ($plans as $plan)
                @php
                    $same = $subscription && $subscription->planId === $plan->id;
                    $current = $same && $subscription->billingCycle === $cycle;
                    $amount = (float) match ($cycle) {
                        'QUARTERLY' => $plan->quarterlyPrice,
                        'ANNUALLY' => $plan->annualPrice,
                        default => $plan->monthlyPrice,
                    };
                @endphp
                <article @class(['plan-card', 'current' => $current])>
                    <div>
                        <p class="tier">{{ ucfirst(strtolower($plan->tier)) }}</p>
                        <h2>{{ $plan->name }}</h2>
                        @if ($plan->description)
                            <p>{{ $plan->description }}</p>
                        @endif
                    </div>
                    <p class="price">{{ $price($plan) }} <span>{{ $suffix[$cycle] }}</span></p>
                    <p class="limits">{{ $slot($plan->maxUsers) }} users, {{ $slot($plan->maxBranches) }} {{ (int) $plan->maxBranches === 1 ? 'branch' : 'branches' }}, {{ $slot($plan->maxProducts) }} products</p>
                    @if ($features($plan) !== [])
                        <ul>
                            @foreach ($features($plan) as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($current)
                        <p class="current-label">Current plan</p>
                    @elseif ($canChange)
                        <form method="post" action="{{ route('billing.choose') }}">
                            @csrf
                            <input type="hidden" name="plan" value="{{ $plan->id }}">
                            <input type="hidden" name="cycle" value="{{ $cycle }}">
                            @if ($amount > 0 && ! $ready)
                                <button type="button" disabled>Payment key missing</button>
                            @elseif ($same)
                                <button type="submit">Switch to {{ strtolower($cycles[$cycle]) }}</button>
                            @elseif ($subscription)
                                <button type="submit">Switch to this plan</button>
                            @else
                                <button type="submit">Get started</button>
                            @endif
                        </form>
                    @endif
                </article>
            @endforeach
        </div>

        <section class="card orders">
            <div class="invoice-head">
                <h2>Invoices</h2>
                <p>Every bill for this shop.</p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Cycle</th>
                        <th>Period</th>
                        <th class="num">Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td><a href="{{ route('billing.invoice', $invoice) }}">{{ $invoice->invoiceNumber }}</a></td>
                            <td>{{ $cycles[$invoice->billingCycle] ?? $invoice->billingCycle }}</td>
                            <td>{{ $invoice->periodStart?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }} – {{ $invoice->periodEnd?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</td>
                            <td class="num">{{ $money($invoice->amount) }}</td>
                            <td><span class="badge {{ $invoice->status === 'PAID' ? 'active' : ($invoice->status === 'PENDING' ? 'pending' : 'cancelled') }}">{{ $invoiceStatuses[$invoice->status] ?? $invoice->status }}</span></td>
                            <td>{{ $invoice->createdAt?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</td>
                            <td>
                                @if ($invoice->status === 'PENDING' && (float) $invoice->amount > 0 && $canChange)
                                    <a href="{{ route('billing.pay', $invoice) }}">Pay now</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="7">No invoices yet.</td>
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
    </div>
@endsection
