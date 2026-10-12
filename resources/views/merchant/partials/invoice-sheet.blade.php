@php
    $amount = (float) $invoice->amount;
    $currency = $invoice->currency ?: 'NGN';
    $money = fn (float $value) => '₦'.number_format($value, 2);
    $date = fn ($value) => $value?->timezone('Africa/Lagos')->format('d M Y') ?: '—';
    $cycle = $cycles[$invoice->billingCycle] ?? ucfirst(strtolower((string) $invoice->billingCycle));
    $isPaid = $invoice->status === 'PAID';
    $isSettled = in_array($invoice->status, ['PAID', 'WAIVED'], true);
    $paid = $isPaid ? $amount : 0.0;
    $balance = $isSettled || $invoice->status === 'REFUNDED' ? 0.0 : $amount;
    $stamp = match ($invoice->status) {
        'PAID' => ['Paid', 'paid'],
        'PENDING' => ['Unpaid', 'unpaid'],
        'FAILED' => ['Payment failed', 'failed'],
        'REFUNDED' => ['Refunded', 'refunded'],
        'WAIVED' => ['Waived', 'paid'],
        default => [$invoiceStatuses[$invoice->status] ?? $invoice->status, 'unpaid'],
    };
    $paymentRef = $isPaid && $invoice->gatewayRef && $invoice->gatewayRef !== $plan?->id ? $invoice->gatewayRef : null;
@endphp

@once
    <style>
        .inv-sheet { position: relative; overflow: hidden; max-width: 860px; margin: 0 auto; padding: 2.25rem; color: #111827; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px; line-height: 1.5; }
        .inv-sheet > * { position: relative; z-index: 1; }
        .inv-sheet .inv-watermark { position: absolute; inset: 0; z-index: 0; display: grid; place-items: center; pointer-events: none; opacity: 0.06; }
        .inv-sheet .inv-watermark img { width: min(26rem, 70%); transform: rotate(-28deg); }
        .inv-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1.5rem; padding-bottom: 1.25rem; border-bottom: 2px solid #16a34a; }
        .inv-brand { display: flex; align-items: center; gap: 0.6rem; }
        .inv-brand img { width: 2.5rem; height: 2.5rem; object-fit: contain; }
        .inv-brand strong { display: block; color: #16a34a; font-size: 1.15rem; letter-spacing: 0.01em; }
        .inv-brand span { display: block; color: #6b7280; font-size: 0.75rem; }
        .inv-title { text-align: right; }
        .inv-title h1 { margin: 0; font-size: 1.6rem; letter-spacing: 0.08em; text-transform: uppercase; }
        .inv-title p { margin: 0.15rem 0 0; color: #4b5563; font-size: 0.85rem; }
        .inv-stamp { display: inline-block; margin-top: 0.5rem; padding: 0.2rem 0.7rem; border: 2px solid currentColor; border-radius: 4px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; }
        .inv-stamp.paid { color: #16a34a; }
        .inv-stamp.unpaid { color: #d97706; }
        .inv-stamp.failed { color: #dc2626; }
        .inv-stamp.refunded { color: #6b7280; }
        .inv-parties { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 1.25rem; margin: 1.5rem 0; }
        .inv-parties h2, .inv-meta h2 { margin: 0 0 0.35rem; color: #6b7280; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; }
        .inv-parties p { margin: 0; color: #4b5563; font-size: 0.85rem; }
        .inv-parties p.name { color: #111827; font-size: 1rem; font-weight: 700; }
        .inv-parties p.regs { color: #6b7280; font-size: 0.78rem; }
        .inv-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; padding: 0.9rem 1rem; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; }
        .inv-meta h2 { margin-bottom: 0.15rem; }
        .inv-meta strong { font-size: 0.88rem; word-break: break-all; }
        .inv-items { width: 100%; border-collapse: collapse; }
        .inv-items th { padding: 0.6rem 0.5rem; color: #fff; background: #16a34a; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.04em; text-align: left; text-transform: uppercase; }
        .inv-items td { padding: 0.75rem 0.5rem; border-bottom: 1px solid #e5e7eb; font-size: 0.88rem; vertical-align: top; }
        .inv-items .num { text-align: right; white-space: nowrap; }
        .inv-items td small { display: block; color: #6b7280; font-size: 0.78rem; }
        .inv-totals { margin: 1rem 0 0 auto; width: min(100%, 20rem); }
        .inv-totals div { display: flex; justify-content: space-between; gap: 1rem; padding: 0.35rem 0; font-size: 0.88rem; }
        .inv-totals div.grand { margin-top: 0.25rem; padding-top: 0.6rem; border-top: 2px solid #111827; font-size: 1.05rem; font-weight: 700; }
        .inv-totals div.due { color: #b45309; font-weight: 700; }
        .inv-notes { margin-top: 1.75rem; color: #4b5563; font-size: 0.82rem; }
        .inv-notes h2 { margin: 0 0 0.3rem; color: #111827; font-size: 0.85rem; }
        .inv-notes p { margin: 0.15rem 0; }
        .inv-foot { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; margin-top: 1.75rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 0.72rem; }
        .inv-foot code { font-size: 0.7rem; }
        @media (max-width: 640px) {
            .inv-sheet { padding: 1.25rem; }
            .inv-head { flex-direction: column; }
            .inv-title { text-align: left; }
        }
        @media print {
            .inv-sheet { max-width: none; padding: 0; border: 0; }
            .inv-sheet .inv-watermark { opacity: 0.08; }
            .inv-items th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
@endonce

<article class="inv-sheet" aria-label="Invoice {{ $invoice->invoiceNumber }}">
    <div class="inv-watermark" aria-hidden="true"><img src="{{ asset('SalesDock.svg') }}" alt=""></div>

    <header class="inv-head">
        <div class="inv-brand">
            <img src="{{ asset('salesdockfavicon.png') }}?v={{ filemtime(public_path('salesdockfavicon.png')) }}" alt="SalesDock">
            <div>
                <strong>SalesDock</strong>
                <span>by {{ $company['name'] }}</span>
            </div>
        </div>
        <div class="inv-title">
            <h1>Invoice</h1>
            <p>{{ $invoice->invoiceNumber }}</p>
            <span class="inv-stamp {{ $stamp[1] }}">{{ $stamp[0] }}</span>
        </div>
    </header>

    <section class="inv-parties">
        <div>
            <h2>Billed by</h2>
            <p class="name">{{ $company['name'] }}</p>
            <p>SalesDock</p>
            @if (! empty($company['address']))
                <p>{{ $company['address'] }}</p>
            @endif
            <p>{{ collect([$company['email'] ?? null, $company['phone'] ?? null])->filter()->implode(' · ') }}</p>
            @if (! empty($company['website']))
                <p>{{ $company['website'] }}</p>
            @endif
            @if (! empty($company['rc']) || ! empty($company['tin']))
                <p class="regs">{{ collect([! empty($company['rc']) ? 'RC '.$company['rc'] : null, ! empty($company['tin']) ? 'TIN '.$company['tin'] : null])->filter()->implode(' · ') }}</p>
            @endif
        </div>
        <div>
            <h2>Billed to</h2>
            <p class="name">{{ $tenant->name }}</p>
            @if ($ownerName)
                <p>Attn: {{ $ownerName }}</p>
            @endif
            @if ($tenant->address)
                <p>{{ $tenant->address }}</p>
            @endif
            @if ($tenant->phone || $tenant->email)
                <p>{{ collect([$tenant->email, $tenant->phone])->filter()->implode(' · ') }}</p>
            @endif
            @if ($tenant->rcNumber || $tenant->tin)
                <p class="regs">{{ collect([$tenant->rcNumber ? 'RC '.$tenant->rcNumber : null, $tenant->tin ? 'TIN '.$tenant->tin : null])->filter()->implode(' · ') }}</p>
            @endif
        </div>
    </section>

    <section class="inv-meta">
        <div><h2>Invoice date</h2><strong>{{ $date($invoice->createdAt) }}</strong></div>
        <div><h2>{{ $isPaid ? 'Date paid' : 'Due date' }}</h2><strong>{{ $isPaid ? $date($invoice->paidAt) : $date($invoice->dueDate ?? $invoice->createdAt) }}</strong></div>
        <div><h2>Billing cycle</h2><strong>{{ $cycle }}</strong></div>
        <div><h2>Currency</h2><strong>{{ $currency }}</strong></div>
        @if ($paymentRef)
            <div><h2>Payment ref</h2><strong>{{ ucfirst(strtolower((string) $invoice->gateway)) }} #{{ $paymentRef }}</strong></div>
        @endif
    </section>

    <table class="inv-items">
        <thead>
            <tr>
                <th>Description</th>
                <th>Period</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    SalesDock {{ $plan?->name ?? '' }} plan
                    <small>{{ $cycle }} subscription{{ $plan?->description ? ' · '.$plan->description : '' }}</small>
                </td>
                <td>{{ $date($invoice->periodStart) }} – {{ $date($invoice->periodEnd) }}</td>
                <td class="num">1</td>
                <td class="num">{{ $money($amount) }}</td>
                <td class="num">{{ $money($amount) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="inv-totals">
        <div><span>Subtotal</span><span>{{ $money($amount) }}</span></div>
        <div class="grand"><span>Total</span><span>{{ $money($amount) }}</span></div>
        <div><span>Amount paid</span><span>{{ $money($paid) }}</span></div>
        <div @class(['due' => $balance > 0])><span>Balance due</span><span>{{ $money($balance) }}</span></div>
    </div>

    <section class="inv-notes">
        <h2>{{ $isPaid ? 'Thank you for your payment' : 'Payment' }}</h2>
        @if ($isPaid)
            <p>This invoice has been paid in full. Your subscription runs until {{ $date($invoice->periodEnd) }}.</p>
        @elseif ($invoice->status === 'PENDING')
            <p>Pay securely from Billing in your SalesDock dashboard by card, bank transfer or USSD.</p>
        @elseif ($invoice->failureReason)
            <p>{{ $invoice->failureReason }}</p>
        @endif
        <p>Questions about this invoice? Contact {{ $company['email'] }}.</p>
    </section>

    <footer class="inv-foot">
        <span>{{ $company['name'] }} · SalesDock subscription invoice · Generated {{ now('Africa/Lagos')->format('d M Y · h:i A') }} WAT</span>
        <code>{{ $docHash }}</code>
    </footer>
</article>
