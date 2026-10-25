<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryEvent extends Model
{
    protected $table = 'delivery_events';

    public static $snakeAttributes = false;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = null;

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
            'payload' => 'array',
            'occurredAt' => 'datetime',
            'createdAt' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'deliveryId');
    }
}
