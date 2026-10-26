<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\PosPayments;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:reconcile-pos')]
#[Description('Verify pending in-store payments and recover missed payment notifications')]
class ReconcilePosPayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PosPayments $payments): int
    {
        $count = 0;
        $failures = 0;
        Order::query()->where('paymentVerificationRequired', true)->whereIn('status', ['PENDING', 'CANCELLED'])
            ->where('createdAt', '>=', now()->subDays(30))
            ->where('updatedAt', '<=', now()->subMinute())
            ->orderBy('updatedAt')->orderBy('id')->limit(25)
            ->each(function (Order $order) use ($payments, &$count, &$failures): void {
                try {
                    $payments->reconcile($order);
                    $count++;
                } catch (\Throwable $exception) {
                    report($exception);
                    $failures++;
                } finally {
                    $order->touch();
                }
            });
        $this->info("Checked {$count} payments; {$failures} could not be verified yet.");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
