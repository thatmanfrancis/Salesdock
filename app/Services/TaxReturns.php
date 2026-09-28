<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\SalesLedger;
use App\Models\TaxFiling;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

class TaxReturns
{
    public function ensure(string $tenantId): array
    {
        $created = [];
        $existing = TaxFiling::query()->where('tenantId', $tenantId)->get()->keyBy('period');

        foreach ($this->closedMonthsWithSales($tenantId) as $period) {
            $filing = $existing->get($period);
            if (! $filing) {
                $made = $this->create($tenantId, $period);
                if ($made) {
                    $created[] = $made;
                }
                continue;
            }
            if ($filing->status !== 'FILED') {
                $this->refresh($filing);
            }
        }

        return $created;
    }

    public function totals(string $tenantId, string $period): array
    {
        [$start, $end] = $this->bounds($period);
        $row = SalesLedger::query()
            ->where('tenantId', $tenantId)
            ->whereBetween('createdAt', [$start, $end])
            ->toBase()
            ->selectRaw('coalesce(sum("grossRevenue"), 0) as gross, coalesce(sum("totalVat"), 0) as vat, coalesce(sum("netRevenue"), 0) as net, count(*) as sales')
            ->first();

        return [
            'gross' => (float) ($row->gross ?? 0),
            'vat' => (float) ($row->vat ?? 0),
            'net' => (float) ($row->net ?? 0),
            'sales' => (int) ($row->sales ?? 0),
        ];
    }

    public function bounds(string $period): array
    {
        $start = Carbon::parse($period.'-01', 'Africa/Lagos')->startOfMonth();

        return [$start->copy()->utc(), $start->copy()->endOfMonth()->utc()];
    }

    public function dueDate(string $period): Carbon
    {
        return Carbon::parse($period.'-01', 'Africa/Lagos')->startOfMonth()->addMonth()->day(21)->endOfDay()->utc();
    }

    public function ledger(string $tenantId, string $period)
    {
        [$start, $end] = $this->bounds($period);

        return SalesLedger::query()
            ->where('tenantId', $tenantId)
            ->whereBetween('createdAt', [$start, $end])
            ->latest('createdAt');
    }

    private function closedMonthsWithSales(string $tenantId): array
    {
        $cutoff = Carbon::now('Africa/Lagos')->startOfMonth()->utc();

        return SalesLedger::query()
            ->where('tenantId', $tenantId)
            ->where('createdAt', '<', $cutoff)
            ->toBase()
            ->selectRaw("to_char((\"createdAt\" AT TIME ZONE 'UTC') AT TIME ZONE 'Africa/Lagos', 'YYYY-MM') as period")
            ->groupByRaw("to_char((\"createdAt\" AT TIME ZONE 'UTC') AT TIME ZONE 'Africa/Lagos', 'YYYY-MM')")
            ->havingRaw('coalesce(sum("grossRevenue"), 0) > 0')
            ->orderBy('period')
            ->pluck('period')
            ->filter(fn ($period) => is_string($period) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period))
            ->values()
            ->all();
    }

    private function create(string $tenantId, string $period): ?TaxFiling
    {
        $totals = $this->totals($tenantId, $period);
        $due = $this->dueDate($period);
        try {
            $filing = TaxFiling::query()->create([
                'tenantId' => $tenantId,
                'period' => $period,
                'grossSales' => $totals['gross'],
                'totalVat' => $totals['vat'],
                'netSales' => $totals['net'],
                'dueDate' => $due,
                'status' => now()->gt($due) ? 'OVERDUE' : 'PENDING',
                'notes' => 'Built from the sales ledger.',
            ]);
        } catch (QueryException) {
            return null;
        }

        Notification::query()->create([
            'tenantId' => $tenantId,
            'type' => 'TAX_REMINDER',
            'title' => 'Tax filing for '.$period,
            'message' => 'The VAT return for '.$period.' is ready. VAT due ₦'.number_format($totals['vat'], 2).'. Deadline '.$due->timezone('Africa/Lagos')->format('d M Y').'.',
            'entityId' => $filing->id,
            'isRead' => false,
            'createdAt' => now(),
        ]);

        return $filing;
    }

    private function refresh(TaxFiling $filing): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $filing->period)) {
            return;
        }

        $totals = $this->totals($filing->tenantId, $filing->period);
        $due = $this->dueDate($filing->period);
        $filing->forceFill([
            'grossSales' => $totals['gross'],
            'totalVat' => $totals['vat'],
            'netSales' => $totals['net'],
            'dueDate' => $due,
            'status' => now()->gt($due) ? 'OVERDUE' : 'PENDING',
        ])->save();
    }
}
