<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesLedger extends Model
{
    protected $table = 'sales_ledger';

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

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashierId');
    }

    public function items()
    {
        return $this->hasMany(SalesLedgerItem::class, 'ledgerId');
    }

    protected function casts(): array
    {
        return [
            'grossRevenue' => 'decimal:2',
            'netRevenue' => 'decimal:2',
            'totalVat' => 'decimal:2',
            'totalCogs' => 'decimal:2',
            'grossProfit' => 'decimal:2',
            'createdAt' => 'datetime',
        ];
    }
}
