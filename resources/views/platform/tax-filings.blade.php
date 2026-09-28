@extends('layouts.app')

@section('title', 'Tax filings | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $show = fn ($value) => filled($value) ? $value : '—';
    $when = fn ($date) => $date?->timezone('Africa/Lagos')->format('d M Y') ?: '—';
    $cycles = [
        'MONTHLY' => 'Monthly',
        'QUARTERLY' => 'Quarterly',
        'ANNUALLY' => 'Annually',
    ];
    $subLabels = [
        'TRIALING' => 'Trialing',
        'ACTIVE' => 'Active',
        'PAST_DUE' => 'Past due',
        'SUSPENDED' => 'Suspended',
        'CANCELLED' => 'Cancelled',
        'EXPIRED' => 'Expired',
    ];
@endphp

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Tax filings</h1>
                <span class="tally">{{ number_format($total) }} total</span>
            </div>
            <x-ui.filter :action="route('admin.tax-filings')" :active="$filtered" label="Filter filings">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Shop name" maxlength="80">
                </div>
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'PENDING' => 'Pending', 'FILED' => 'Filed', 'OVERDUE' => 'Overdue']" />
                <x-ui.select name="year" label="Year" :value="$year" :options="$years" />
            </x-ui.filter>
        </div>

        <div class="ledger-line">
            @foreach ($cards as $card)
                <article>
                    <span>{{ $card['label'] }}</span>
                    <strong @class([$card['class'] ?? '']) title="{{ $card['value'] }}">{{ $card['value'] }}</strong>
                </article>
            @endforeach
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Shop</th>
                        <th>Period</th>
                        <th class="num">Gross</th>
                        <th class="num">VAT</th>
                        <th class="num">Net</th>
                        <th>Status</th>
                        <th>Due</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filings as $filing)
                        @php
                            $tenant = $filing->tenant;
                            $subscription = $tenant?->subscription;
                            $plan = $subscription?->plan;
                            $cycle = $subscription?->billingCycle;
                            $price = match ($cycle) {
                                'QUARTERLY' => $plan?->quarterlyPrice,
                                'ANNUALLY' => $plan?->annualPrice,
                                default => $plan?->monthlyPrice,
                            };
                            $detail = [
                                'period' => \Illuminate\Support\Carbon::parse($filing->period.'-01')->format('F Y'),
                                'status' => ucfirst(strtolower($filing->status)),
                                'tone' => strtolower($filing->status),
                                'gross' => $money($filing->grossSales),
                                'vat' => $money($filing->totalVat),
                                'net' => $money($filing->netSales),
                                'due' => $when($filing->dueDate),
                                'shop' => $show($tenant?->name),
                                'slug' => $show($tenant?->slug),
                                'email' => $show($tenant?->email),
                                'phone' => $show($tenant?->phone),
                                'address' => $show($tenant?->address),
                                'tin' => $show($tenant?->tin),
                                'registered' => $when($tenant?->createdAt),
                                'shopStatus' => $tenant ? ($tenant->isActive ? 'Active' : 'Inactive') : '—',
                                'plan' => $plan?->name ?? '',
                                'subStatus' => $subLabels[$subscription?->status] ?? ($subscription?->status ?: '—'),
                                'price' => $plan ? $money($price) : '—',
                                'cycle' => $cycles[$cycle] ?? ($cycle ?: '—'),
                                'subStart' => $when($subscription?->currentPeriodStart),
                                'subEnd' => $when($subscription?->currentPeriodEnd),
                            ];
                        @endphp
                        <tr @class(['late' => $filing->status === 'OVERDUE'])>
                            <td>
                                @if ($tenant)
                                    <a href="{{ route('admin.tenants.show', $tenant) }}">{{ $tenant->name }}</a>
                                    <div class="muted">{{ $tenant->slug }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $detail['period'] }}</td>
                            <td class="num">{{ $detail['gross'] }}</td>
                            <td class="num vat">{{ $detail['vat'] }}</td>
                            <td class="num">{{ $detail['net'] }}</td>
                            <td><span class="badge {{ $detail['tone'] }}">{{ $detail['status'] }}</span></td>
                            <td>{{ $detail['due'] }}</td>
                            <td><button type="button" class="detail" data-filing="{{ json_encode($detail, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) }}">Details</button></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="8">No tax filings.</td>
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

    <div class="ui-modal" data-filing-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <div class="ui-modal-sheet tall">
            <div class="ui-filter-head">
                <strong>Filing details</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <div class="block">
                <h2>Filing</h2>
                <div class="supplier-facts">
                    <div><span>Period</span><strong data-slot="period"></strong></div>
                    <div><span>Status</span><strong><span class="badge" data-slot="status"></span></strong></div>
                    <div><span>Gross sales</span><strong data-slot="gross"></strong></div>
                    <div><span>VAT</span><strong class="vat" data-slot="vat"></strong></div>
                    <div><span>Net sales</span><strong data-slot="net"></strong></div>
                    <div><span>Due date</span><strong data-slot="due"></strong></div>
                </div>
            </div>
            <div class="block">
                <h2>Shop</h2>
                <div class="supplier-facts">
                    <div><span>Name</span><strong data-slot="shop"></strong></div>
                    <div><span>Slug</span><strong data-slot="slug"></strong></div>
                    <div><span>Email</span><strong data-slot="email"></strong></div>
                    <div><span>Phone</span><strong data-slot="phone"></strong></div>
                    <div class="wide"><span>Address</span><strong data-slot="address"></strong></div>
                    <div><span>TIN</span><strong data-slot="tin"></strong></div>
                    <div><span>Registered</span><strong data-slot="registered"></strong></div>
                    <div><span>Status</span><strong data-slot="shopStatus"></strong></div>
                </div>
            </div>
            <div class="block" data-subscription>
                <h2>Subscription</h2>
                <div class="supplier-facts">
                    <div><span>Plan</span><strong data-slot="plan"></strong></div>
                    <div><span>Status</span><strong data-slot="subStatus"></strong></div>
                    <div><span>Price</span><strong data-slot="price"></strong></div>
                    <div><span>Cycle</span><strong data-slot="cycle"></strong></div>
                    <div><span>Period starts</span><strong data-slot="subStart"></strong></div>
                    <div><span>Period ends</span><strong data-slot="subEnd"></strong></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const modal = document.querySelector('[data-filing-modal]');
        const closeModal = () => { modal.hidden = true; };
        document.querySelectorAll('[data-filing]').forEach((button) => {
            button.addEventListener('click', () => {
                const data = JSON.parse(button.dataset.filing);
                modal.querySelectorAll('[data-slot]').forEach((node) => {
                    const value = data[node.dataset.slot] || '—';
                    if (node.dataset.slot === 'status') {
                        node.className = 'badge ' + (data.tone || '');
                    }
                    node.textContent = value;
                });
                modal.querySelector('[data-subscription]').hidden = !data.plan;
                modal.hidden = false;
            });
        });
        modal.querySelectorAll('[data-close]').forEach((button) => {
            button.addEventListener('click', closeModal);
        });
    </script>
@endsection
