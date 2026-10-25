<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $table = 'deliveries';

    public static $snakeAttributes = false;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->getKey()) {
                $model->{$model->getKeyName()} = (string) str()->ulid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'quotedFee' => 'decimal:2',
            'actualFee' => 'decimal:2',
            'weightKg' => 'decimal:2',
            'pickup' => 'array',
            'recipient' => 'array',
            'providerPayload' => 'array',
            'trackingHistory' => 'array',
            'bookedAt' => 'datetime',
            'deliveredAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'orderId');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(DeliveryAccount::class, 'deliveryAccountId');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class, 'deliveryId')->latest('occurredAt');
    }

    public function customerStatus(): string
    {
        return match ($this->status) {
            'AWAITING_FEZ_PAYMENT', 'BOOKING_FAILED', 'BOOKING_UNCERTAIN' => 'The shop is arranging delivery.',
            'NOT_BOOKED' => 'Delivery has not been booked yet.',
            'BOOKED', 'PENDING_PICKUP' => 'Delivery booked — waiting for pickup.',
            'PICKED_UP', 'DISPATCHED', 'IN_TRANSIT' => 'Your order is on its way.',
            'DELIVERED' => 'Delivered.',
            'RETURNED' => 'Delivery was returned — please contact the shop.',
            'CANCELLED' => 'Delivery was cancelled.',
            default => 'Delivery update: '.str_replace('_', ' ', strtolower((string) $this->status)),
        };
    }
}
