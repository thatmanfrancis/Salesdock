<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $table = 'order_items';

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

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unitPrice' => 'decimal:2',
            'originalPrice' => 'decimal:2',
            'discountAmount' => 'decimal:2',
            'lineTotal' => 'decimal:2',
            'isPriceOverridden' => 'boolean',
            'createdAt' => 'datetime',
        ];
    }
}
