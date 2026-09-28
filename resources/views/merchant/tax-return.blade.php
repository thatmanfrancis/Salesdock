<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>VAT return {{ $filing->period }} | SalesDock</title>
    <link rel="icon" href="{{ asset('SalesDock.svg') }}" type="image/svg+xml">
    <style>
        body { margin: 0; color: #111827; font: 14px/1.5 "Space Grotesk", sans-serif; }
        main { max-width: 800px; margin: 0 auto; padding: 2rem; }
        header { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; }
        h1 { margin: 0; font-size: 1.4rem; }
        p { margin: 0.15rem 0; }
        .muted { color: #6b7280; }
        .totals { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin: 1.25rem 0; }
        .totals div { padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 0.75rem; }
        .totals span { display: block; color: #6b7280; font-size: 0.75rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.45rem 0.35rem; border-bottom: 1px solid #e5e7eb; text-align: left; font-size: 0.8rem; }
        th.num, td.num { text-align: right; }
        button { margin-top: 1rem; padding: 0.5rem 0.8rem; color: #fff; background: #16a34a; border: 0; border-radius: 0.4rem; }
        @media print { button { display: none; } main { padding: 0; } }
    </style>
</head>
<body>
    @php
        $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
        $label = \Illuminate\Support\Carbon::parse($filing->period.'-01')->format('F Y');
        $due = $filing->dueDate?->timezone('Africa/Lagos')->format('d M Y') ?: '—';
    @endphp
    <main>
        <header>
            <div>
                <h1>VAT return</h1>
                <p>{{ $label }}</p>
                <p class="muted">Due {{ $due }}</p>
            </div>
            <div>
                <p><strong>{{ $tenant->name ?? 'Shop' }}</strong></p>
                <p class="muted">TIN {{ $tenant->tin ?: '—' }}</p>
                @if ($tenant->address)
                    <p class="muted">{{ $tenant->address }}</p>
                @endif
            </div>
        </header>
        <div class="totals">
            <div><span>Gross sales</span><strong>{{ $money($totals['gross']) }}</strong></div>
            <div><span>Total VAT</span><strong>{{ $money($totals['vat']) }}</strong></div>
            <div><span>Net sales</span><strong>{{ $money($totals['net']) }}</strong></div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Date</th>
                    <th>Payment</th>
                    <th class="num">Gross</th>
                    <th class="num">VAT</th>
                    <th class="num">Net</th>
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
                    <tr><td colspan="6">No sales in this month.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="muted" style="margin-top: 1rem;">Prepared by SalesDock from the sales ledger. This is the shop’s return to take to FIRS. SalesDock does not collect the VAT.</p>
        <button type="button" onclick="window.print()">Print or save as PDF</button>
    </main>
</body>
</html>
