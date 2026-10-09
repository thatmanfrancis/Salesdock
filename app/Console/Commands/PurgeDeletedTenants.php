<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantEraser;
use Illuminate\Console\Command;

class PurgeDeletedTenants extends Command
{
    protected $signature = 'tenants:purge';

    protected $description = 'Erase shops that were deleted more than '.Tenant::RESTORE_DAYS.' days ago, with everything tied to them';

    public function handle(TenantEraser $eraser): int
    {
        $erased = 0;

        Tenant::onlyTrashed()
            ->where('deletedAt', '<', now()->subDays(Tenant::RESTORE_DAYS))
            ->get()
            ->each(function (Tenant $tenant) use ($eraser, &$erased) {
                try {
                    $eraser->erase($tenant);
                    $erased++;
                    $this->line('Erased: '.$tenant->name);
                } catch (\Throwable $e) {
                    report($e);
                    $this->error('Could not erase '.$tenant->name.': '.$e->getMessage());
                }
            });

        $this->info($erased.' shop(s) erased.');

        return self::SUCCESS;
    }
}
