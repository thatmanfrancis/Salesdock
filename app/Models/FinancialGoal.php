<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialGoal extends Model
{
    protected $table = 'financial_goals';

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
            'targetValue' => 'decimal:2',
            'startDate' => 'datetime',
            'endDate' => 'datetime',
            'isActive' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
