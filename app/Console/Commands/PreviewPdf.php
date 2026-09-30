<?php

namespace App\Console\Commands;

use App\Models\SalesLedger;
use App\Models\StaffPayroll;
use App\Models\TaxFiling;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TaxReturns;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PreviewPdf extends Command
{
    protected $signature = 'pdf:preview
        {type : tax-return|payslip|analytics|financials}
        {--tenant= : Tenant slug or ID (optional)}
        {--days=30 : Day range for analytics/financials previews}';

    protected $description = 'Print a preview URL for a PDF document and render it locally to catch errors';

    public function handle(): int
    {
        $type = $this->argument('type');
        $allowed = ['tax-return', 'payslip', 'analytics', 'financials'];

        if (! in_array($type, $allowed, true)) {
            $this->error('Type must be one of: '.implode(', ', $allowed).'.');

            return self::FAILURE;
        }

        $slug = $this->option('tenant');
        $tenant = $slug
            ? Tenant::query()->where('slug', $slug)->orWhere('id', $slug)->first()
            : Tenant::query()->where('approvalStatus', 'APPROVED')->orderBy('createdAt')->first();

        if (! $tenant) {
            $this->error('No tenant found. Use --tenant=slug to specify one.');

            return self::FAILURE;
        }

        $url = null;

        if ($type === 'tax-return') {
            $filing = TaxFiling::query()
                ->where('tenantId', $tenant->id)
                ->orderByDesc('period')
                ->first();

            if (! $filing) {
                $this->warn('No tax filings found for '.$tenant->name.'. Generate them first:');
                $this->line('  php artisan tax-filings:generate');

                return self::FAILURE;
            }

            $url = route('tax-filings.pdf', $filing);
            $this->info('Tax return PDF for '.$tenant->name.' ('.$filing->period.')');
        }

        if ($type === 'payslip') {
            $payroll = StaffPayroll::query()
                ->where('tenantId', $tenant->id)
                ->with('user')
                ->orderByDesc('createdAt')
                ->first();

            if (! $payroll) {
                $this->warn('No payroll records found for '.$tenant->name.'.');

                return self::FAILURE;
            }

            $url = route('payroll.print', $payroll);
            $this->info('Payslip PDF for '.($payroll->user?->name ?? 'staff').' · '.$payroll->period);
        }

        if ($type === 'analytics') {
            $url = route('analytics.pdf', ['period' => (string) max(7, (int) $this->option('days'))]);
            $this->info('Analytics report PDF for '.$tenant->name);
        }

        if ($type === 'financials') {
            $days = max(1, (int) $this->option('days'));
            $to = now('Africa/Lagos')->toDateString();
            $from = now('Africa/Lagos')->subDays($days - 1)->toDateString();
            $url = route('financials.pdf', ['from' => $from, 'to' => $to]);
            $this->info('Financials report PDF for '.$tenant->name);
        }

        $this->line('Open this URL in your browser (must be logged in as the tenant owner):');
        $this->line('  '.$url);

        $this->newLine();
        $this->line('Rendering locally to check for errors...');

        try {
            $html = match ($type) {
                'tax-return' => $this->renderTaxReturn($tenant),
                'payslip' => $this->renderPayslip($tenant),
                'analytics' => $this->renderAnalytics($tenant),
                'financials' => $this->renderFinancials($tenant),
            };
            $this->info('  Rendered OK — '.strlen($html).' chars');
        } catch (\Throwable $e) {
            $this->error('  Render error: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function renderTaxReturn(Tenant $tenant): string
    {
        $filing = TaxFiling::query()->where('tenantId', $tenant->id)->orderByDesc('period')->firstOrFail();
        $returns = app(TaxReturns::class);

        return view('merchant.tax-return', [
            'filing' => $filing,
            'tenant' => $tenant,
            'entries' => $returns->ledger($tenant->id, $filing->period)->get(),
            'totals' => $returns->totals($tenant->id, $filing->period),
        ])->render();
    }

    private function renderPayslip(Tenant $tenant): string
    {
        $payroll = StaffPayroll::query()
            ->where('tenantId', $tenant->id)
            ->with('user')
            ->orderByDesc('createdAt')
            ->firstOrFail();

        return view('merchant.payslip', [
            'payroll' => $payroll,
            'tenant' => $tenant,
        ])->render();
    }

    private function renderAnalytics(Tenant $tenant): string
    {
        $days = max(7, (int) $this->option('days'));
        $end = now('Africa/Lagos')->endOfDay();
        $start = $end->copy()->subDays($days - 1)->startOfDay();
        $totals = $this->ledgerTotals($tenant->id, $start->copy()->utc(), $end->copy()->utc());
        $gross = $totals['gross'];
        $range = $start->format('d M Y').' – '.$end->format('d M Y');

        return view('merchant.analytics-report', $this->reportBrand($tenant, [
            'title' => 'Analytics report',
            'range' => $range,
            'backUrl' => route('analytics'),
            'fingerprint' => implode('|', [
                'analytics',
                (string) $days,
                $start->toIso8601String(),
                $end->toIso8601String(),
                (string) $gross,
                (string) $totals['profit'],
                (string) $totals['orders'],
            ]),
            'kpis' => [
                'gross' => $gross,
                'cogs' => $totals['cogs'],
                'vat' => $totals['vat'],
                'profit' => $totals['profit'],
                'net' => $totals['net'],
                'orders' => $totals['orders'],
                'aov' => $totals['orders'] > 0 ? $gross / $totals['orders'] : 0,
                'margin' => $gross > 0 ? round(($totals['profit'] / $gross) * 100, 1) : 0,
            ],
            'channels' => [],
            'dayparts' => [],
            'branches' => [],
            'products' => [],
            'period' => (string) $days,
        ]))->render();
    }

    private function renderFinancials(Tenant $tenant): string
    {
        $days = max(1, (int) $this->option('days'));
        $end = now('Africa/Lagos')->endOfDay();
        $start = $end->copy()->subDays($days - 1)->startOfDay();
        $query = SalesLedger::query()
            ->where('tenantId', $tenant->id)
            ->where('createdAt', '>=', $start->copy()->utc())
            ->where('createdAt', '<=', $end->copy()->utc());
        $totals = (clone $query)->toBase()->selectRaw(
            'coalesce(sum("grossRevenue"), 0) as gross_revenue, coalesce(sum("netRevenue"), 0) as net_revenue, coalesce(sum("totalVat"), 0) as total_vat, coalesce(sum("grossProfit"), 0) as gross_profit'
        )->first();
        $entries = $query->with('cashier')->latest('createdAt')->limit(50)->get();
        $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
        $range = $start->format('d M Y').' – '.$end->format('d M Y');

        return view('merchant.financials-report', $this->reportBrand($tenant, [
            'title' => 'Financials report',
            'range' => $range,
            'backUrl' => route('financials'),
            'fingerprint' => implode('|', [
                'financials',
                $range,
                (string) ($totals->gross_revenue ?? 0),
                (string) ($totals->net_revenue ?? 0),
                (string) ($totals->total_vat ?? 0),
                (string) ($totals->gross_profit ?? 0),
                (string) $entries->count(),
            ]),
            'summary' => [
                ['label' => 'Gross revenue', 'value' => $money($totals->gross_revenue ?? 0)],
                ['label' => 'Net revenue', 'value' => $money($totals->net_revenue ?? 0)],
                ['label' => 'Total VAT', 'value' => $money($totals->total_vat ?? 0)],
                ['label' => 'Gross profit', 'value' => $money($totals->gross_profit ?? 0)],
            ],
            'entries' => $entries,
            'filters' => ['Last '.$days.' days'],
        ]))->render();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function reportBrand(Tenant $tenant, array $data): array
    {
        $owner = $this->ownerName($tenant);
        $fingerprint = (string) ($data['fingerprint'] ?? (($data['title'] ?? 'report').'|'.($data['range'] ?? '')));
        unset($data['fingerprint']);
        $hash = strtoupper(substr(hash('sha256', $tenant->id.'|'.$fingerprint.'|'.config('app.key')), 0, 16));

        return array_merge($data, [
            'businessName' => $tenant->name,
            'ownerName' => $owner,
            'preparedBy' => $owner,
            'generatedAt' => now('Africa/Lagos')->format('d M Y · h:i A').' WAT',
            'businessAddress' => $tenant->address,
            'businessPhone' => $tenant->phone,
            'businessEmail' => $tenant->email,
            'businessTin' => $tenant->tin,
            'businessRc' => $tenant->rcNumber,
            'docHash' => 'DOC-'.$hash,
        ]);
    }

    /**
     * @return array{gross: float, cogs: float, vat: float, profit: float, net: float, orders: int}
     */
    private function ledgerTotals(string $tenantId, Carbon $start, Carbon $end): array
    {
        $row = SalesLedger::query()
            ->where('tenantId', $tenantId)
            ->whereBetween('createdAt', [$start, $end])
            ->toBase()
            ->selectRaw('coalesce(sum("grossRevenue"), 0) as gross, coalesce(sum("totalCogs"), 0) as cogs, coalesce(sum("totalVat"), 0) as vat, coalesce(sum("grossProfit"), 0) as profit, coalesce(sum("netRevenue"), 0) as net, count(*) as orders')
            ->first();

        return [
            'gross' => (float) ($row->gross ?? 0),
            'cogs' => (float) ($row->cogs ?? 0),
            'vat' => (float) ($row->vat ?? 0),
            'profit' => (float) ($row->profit ?? 0),
            'net' => (float) ($row->net ?? 0),
            'orders' => (int) ($row->orders ?? 0),
        ];
    }

    private function ownerName(Tenant $tenant): string
    {
        return User::query()
            ->where('tenantId', $tenant->id)
            ->whereHas('role', fn ($query) => $query->where('name', 'Owner'))
            ->orderBy('createdAt')
            ->value('name') ?: 'Owner';
    }
}
