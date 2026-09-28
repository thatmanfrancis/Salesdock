<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxFiling extends Model
{
    protected $table = 'tax_filings';

    public static $snakeAttributes = false;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenantId');
    }

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
            'grossSales' => 'decimal:2',
            'totalVat' => 'decimal:2',
            'netSales' => 'decimal:2',
            'totalTaxPaid' => 'decimal:2',
            'filedAt' => 'datetime',
            'dueDate' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
