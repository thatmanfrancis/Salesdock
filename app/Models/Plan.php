<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plans';

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

    /**
     * Plans a shop can buy. Zero-priced plans are never offered: every shop pays after its trial.
     */
    public function scopePurchasable(Builder $query): Builder
    {
        return $query->where('isActive', true)->where('monthlyPrice', '>', 0);
    }

    protected function casts(): array
    {
        return [
            'monthlyPrice' => 'decimal:2',
            'quarterlyPrice' => 'decimal:2',
            'annualPrice' => 'decimal:2',
            'maxBranches' => 'integer',
            'maxUsers' => 'integer',
            'maxProducts' => 'integer',
            'features' => 'array',
            'isActive' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
