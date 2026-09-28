<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

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
            'price' => 'decimal:2',
            'originalPrice' => 'decimal:2',
            'costPrice' => 'decimal:2',
            'wholesalePrice' => 'decimal:2',
            'vatRateOverride' => 'decimal:2',
            'currentStock' => 'integer',
            'reservedQty' => 'integer',
            'minThreshold' => 'integer',
            'reorderQty' => 'integer',
            'leadTimeDays' => 'integer',
            'isDiscounted' => 'boolean',
            'expiresAt' => 'datetime',
            'isActive' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
