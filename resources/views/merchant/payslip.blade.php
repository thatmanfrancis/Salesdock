<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $payroll->period }} | SalesDock</title>
    <style>
        body { margin: 0; color: #111827; font: 14px/1.5 "Space Grotesk", sans-serif; }
        main { max-width: 420px; margin: 2.5rem auto; padding: 1.5rem; }
        h1 { margin: 0; text-align: center; font-size: 1.4rem; }
        .sub { margin: 0.25rem 0 1.25rem; color: #6b7280; text-align: center; font-size: 0.85rem; }
        .row { display: flex; justify-content: space-between; gap: 1rem; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6; }
        .row.total { margin-top: 0.35rem; border-top: 2px solid #111827; border-bottom: 0; font-weight: 700; font-size: 1.05rem; }
        .gain { color: #15803d; }
        .spend { color: #dc2626; }
        .note { margin-top: 1rem; color: #6b7280; font-size: 0.8rem; }
        button { margin-top: 1.25rem; padding: 0.5rem 0.8rem; color: #fff; background: #16a34a; border: 0; border-radius: 0.4rem; }
        @media print { button { display: none; } main { margin: 0; padding: 0; } }
    </style>
</head>
<body>
    @php
        $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
        try {
            $month = \Illuminate\Support\Carbon::createFromFormat('!Y-m', (string) $payroll->period)->format('M Y');
        } catch (\Throwable) {
            $month = $payroll->period;
        }
    @endphp
    <main>
        <h1>Payslip</h1>
        <p class="sub">{{ $tenant->name ?? 'Shop' }} · {{ $month }}</p>
        <div class="row"><span>Staff</span><strong>{{ $payroll->user?->name ?: '—' }}</strong></div>
        <div class="row"><span>Role</span><span>{{ $payroll->user?->role?->name ?: '—' }}</span></div>
        <div class="row"><span>Base salary</span><span>{{ $money($payroll->baseSalary) }}</span></div>
        <div class="row"><span class="gain">Bonus</span><span class="gain">+ {{ $money($payroll->bonus) }}</span></div>
        <div class="row"><span class="spend">Deductions</span><span class="spend">− {{ $money($payroll->deductions) }}</span></div>
        <div class="row total"><span>Net pay</span><span class="gain">{{ $money($payroll->netPay) }}</span></div>
        @if ($payroll->notes)
            <p class="note">Notes: {{ $payroll->notes }}</p>
        @endif
        <p class="note">{{ $payroll->isPaid ? 'Paid' : 'Pending' }} · Processed {{ $payroll->createdAt?->timezone('Africa/Lagos')->format('d M Y') }}</p>
        <button type="button" onclick="window.print()">Print</button>
    </main>
</body>
</html>
