<?php

namespace App\Console\Commands;

use App\Models\BillingInvoice;
use App\Models\Subscription;
use App\Services\RecurringBilling;
use Illuminate\Console\Command;
use Throwable;

class RenewSubscriptions extends Command
{
    protected $signature = 'subscriptions:renew';

    protected $description = 'Reconcile pending automatic payments, remind consenting owners, and submit due card renewals once';

    public function handle(RecurringBilling $billing): int
    {
        $failures = 0;
        BillingInvoice::query()->where('status', 'PENDING')->where('billingMetadata->kind', 'automatic')
            ->whereIn('billingMetadata->chargeState', ['STARTING', 'UNCERTAIN'])
            ->chunkById(50, function ($invoices) use ($billing, &$failures): void {
                foreach ($invoices as $invoice) {
                    try {
                        $billing->reconcile($invoice);
                    } catch (Throwable $exception) {
                        report($exception);
                        $failures++;
                    }
                }
            });
        Subscription::query()->where('autoRenew', true)->whereBetween('currentPeriodEnd', [now(), now()->addDays(3)])
            ->whereNull('recurringRemindedAt')->chunkById(50, function ($subscriptions) use ($billing, &$failures): void {
                foreach ($subscriptions as $subscription) {
                    try {
                        $billing->sendReminder($subscription);
                    } catch (Throwable $exception) {
                        report($exception);
                        $failures++;
                    }
                }
            });
        Subscription::query()->where('autoRenew', true)->where('currentPeriodEnd', '<=', now())
            ->chunkById(50, function ($subscriptions) use ($billing, &$failures): void {
                foreach ($subscriptions as $subscription) {
                    try {
                        if (! $billing->hasUncertainRenewal($subscription->tenantId)) {
                            $billing->process($subscription);
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                        $failures++;
                    }
                }
            });
        $this->info('Subscription renewal processing completed. '.$failures.' unexpected failure(s).');

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
