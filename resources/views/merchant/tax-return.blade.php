<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>VAT Return {{ $filing->period }} | SalesDock</title>
    <link rel="icon" href="{{ asset('SalesDock.svg') }}" type="image/svg+xml">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font: 13px/1.5 'Segoe UI', Arial, sans-serif; background: #fff; }
        main { max-width: 820px; margin: 0 auto; padding: 2rem; }

        /* ── Header ── */
        .doc-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1.5rem; padding-bottom: 1.25rem; border-bottom: 2px solid #16a34a; margin-bottom: 1.5rem; }
        .doc-brand { display: flex; align-items: center; gap: 0.6rem; }
        .doc-brand img { height: 2rem; }
        .doc-brand strong { font-size: 1rem; color: #16a34a; letter-spacing: 0.02em; }
        .doc-title h1 { margin: 0; font-size: 1.3rem; }
        .doc-title p { margin: 0.15rem 0 0; color: #6b7280; font-size: 0.8rem; }
        .doc-tenant { text-align: right; }
        .doc-tenant strong { display: block; font-size: 0.95rem; }
        .doc-tenant span { color: #6b7280; font-size: 0.8rem; }

        /* ── Totals ── */
        .totals { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1.5rem; }
        .totals div { padding: 0.75rem 1rem; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; }
        .totals span { display: block; color: #6b7280; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.2rem; }
        .totals strong { font-size: 1.1rem; }

        /* ── Table ── */
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        th { padding: 0.45rem 0.5rem; background: #f3f4f6; border-bottom: 1px solid #e5e7eb; text-align: left; font-weight: 700; color: #374151; }
        td { padding: 0.4rem 0.5rem; border-bottom: 1px solid #f3f4f6; color: #374151; }
        th.num, td.num { text-align: right; }
        tr:last-child td { border-bottom: 0; }

        /* ── Footer ── */
        .doc-footer { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; }
        .doc-footer p { margin: 0; color: #9ca3af; font-size: 0.7rem; line-height: 1.5; }
        .doc-footer .seal { text-align: right; }
        .doc-footer .seal strong { display: block; font-size: 0.7rem; color: #374151; }
        .doc-footer .seal code { font-size: 0.65rem; color: #9ca3af; word-break: break-all; }

        /* ── Print ── */
        .no-print { display: block; margin-top: 1.25rem; }
        button { padding: 0.55rem 1rem; color: #fff; background: #16a34a; border: 0; border-radius: 0.4rem; font-size: 0.875rem; cursor: pointer; }
        @media print { .no-print { display: none; } main { padding: 0; } }
    </style>
</head>
<body>
@php
    $money   = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $label   = \Illuminate\Support\Carbon::parse($filing->period.'-01')->format('F Y');
    $due     = $filing->dueDate?->timezone('Africa/Lagos')->format('d M Y') ?? '—';
    $genAt   = now()->timezone('Africa/Lagos')->format('d M Y, H:i:s T');
    $hash    = strtoupper(substr(hash('sha256', $filing->id . $filing->period . $filing->totalVat . config('app.key')), 0, 16));
@endphp
<main>
    <div class="doc-header">
        <div class="doc-brand">
            <img src="{{ asset('SalesDock.svg') }}" alt="SalesDock">
            <strong>SalesDock</strong>
        </div>
        <div class="doc-title" style="flex:1;text-align:center">
            <h1>VAT Return</h1>
            <p>{{ $label }} &nbsp;·&nbsp; Due {{ $due }}</p>
        </div>
        <div class="doc-tenant">
            <strong>{{ $tenant->name ?? 'Shop' }}</strong>
            <span>TIN: {{ $tenant->tin ?: '—' }}</span><br>
            @if ($tenant->address)
                <span>{{ $tenant->address }}</span>
            @endif
        </div>
    </div>

    <div class="totals">
        <div><span>Gross sales</span><strong>{{ $money($totals['gross']) }}</strong></div>
        <div><span>Total VAT (7.5%)</span><strong>{{ $money($totals['vat']) }}</strong></div>
        <div><span>Net sales</span><strong>{{ $money($totals['net']) }}</strong></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Order ref</th>
                <th>Date</th>
                <th>Payment method</th>
                <th class="num">Gross (₦)</th>
                <th class="num">VAT (₦)</th>
                <th class="num">Net (₦)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ strtoupper(substr((string) $entry->orderId, -8)) }}</td>
                    <td>{{ $entry->createdAt?->timezone('Africa/Lagos')->format('d M Y') }}</td>
                    <td>{{ $entry->paymentMethod ? ucwords(strtolower(str_replace('_', ' ', $entry->paymentMethod))) : '—' }}</td>
                    <td class="num">{{ $money($entry->grossRevenue) }}</td>
                    <td class="num">{{ $money($entry->totalVat) }}</td>
                    <td class="num">{{ $money($entry->netRevenue) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:1rem">No sales in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="doc-footer">
        <p>
            Prepared by SalesDock from verified sales ledger data.<br>
            This document is for VAT filing at the Nigeria Revenue Service (NRS).<br>
            SalesDock does not collect or remit this VAT on behalf of the merchant.<br>
            Filing ID: {{ $filing->id }} &nbsp;·&nbsp; Status: {{ ucfirst(strtolower($filing->status)) }}
        </p>
        <div class="seal">
            <strong>Generated {{ $genAt }}</strong>
            <code>DOC-{{ $hash }}</code>
        </div>
    </div>

    <div class="no-print">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>
</main>
</body>
</html>
