<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesLedgerItem extends Model
{
    protected $table = 'sales_ledger_items';

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
            'costPrice' => 'decimal:2',
            'sellingPrice' => 'decimal:2',
            'lineTotal' => 'decimal:2',
            'vatPercentage' => 'decimal:2',
            'vatAmount' => 'decimal:2',
            'grossLineTotal' => 'decimal:2',
            'netLineTotal' => 'decimal:2',
            'grossProfit' => 'decimal:2',
        ];
    }
}
