<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TaxReturns;
use Illuminate\Console\Command;

class GenerateTaxFilings extends Command
{
    protected $signature = 'tax-filings:generate';

    protected $description = 'Build last month’s VAT returns from the sales ledger';

    public function handle(TaxReturns $returns): int
    {
        $made = 0;
        Tenant::query()
            ->where('isActive', true)
            ->where('approvalStatus', 'APPROVED')
            ->pluck('id')
            ->each(function (string $tenantId) use ($returns, &$made) {
                $made += count($returns->ensure($tenantId));
            });

        $this->info('Generated '.$made.' tax filings.');

        return self::SUCCESS;
    }
}
