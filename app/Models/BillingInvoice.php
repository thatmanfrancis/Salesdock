<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingInvoice extends Model
{
    protected $table = 'billing_invoices';

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

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenantId');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'periodStart' => 'datetime',
            'periodEnd' => 'datetime',
            'paidAt' => 'datetime',
            'failedAt' => 'datetime',
            'retryCount' => 'integer',
            'nextRetryAt' => 'datetime',
            'dueDate' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
