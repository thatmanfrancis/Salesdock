<?php

namespace App\Console\Commands;

use App\Models\TenantRegistration;
use Illuminate\Console\Command;

class PruneUnverifiedRegistrations extends Command
{
    /**
     * Days an unverified signup is kept before it is removed.
     */
    private const KEEP_DAYS = 7;

    protected $signature = 'registrations:prune';

    protected $description = 'Remove signups that never verified their email within '.self::KEEP_DAYS.' days';

    public function handle(): int
    {
        $removed = TenantRegistration::query()
            ->where('emailVerified', false)
            ->where('createdAt', '<', now()->subDays(self::KEEP_DAYS))
            ->delete();

        $this->info($removed.' unverified signup(s) removed.');

        return self::SUCCESS;
    }
}
