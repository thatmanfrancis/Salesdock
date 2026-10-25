<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Services\DeliveryBooking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('deliveries:sync-fez')]
#[Description('Refresh active Fez delivery statuses and wallet-payment states')]
class SyncFezDeliveries extends Command
{
    public function handle(DeliveryBooking $booking): int
    {
        $count = 0;
        Delivery::query()
            ->where('provider', 'FEZ')
            ->whereNotNull('providerOrderNo')
            ->whereNotIn('status', ['DELIVERED', 'CANCELLED', 'RETURNED'])
            ->orderBy('updatedAt')
            ->limit(100)
            ->each(function (Delivery $delivery) use ($booking, &$count): void {
                $booking->refresh($delivery);
                $count++;
            });

        $this->info("Refreshed {$count} Fez deliveries.");

        return self::SUCCESS;
    }
}
