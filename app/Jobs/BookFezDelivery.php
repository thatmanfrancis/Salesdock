<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Services\DeliveryBooking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BookFezDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $deliveryId) {}

    public function handle(DeliveryBooking $booking): void
    {
        $delivery = Delivery::query()->find($this->deliveryId);
        if (! $delivery || in_array($delivery->status, ['DELIVERED', 'CANCELLED', 'RETURNED'], true)) {
            return;
        }

        $booking->book($delivery);
    }
}
