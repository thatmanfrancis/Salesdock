<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $table = 'subscriptions';

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

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'planId');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenantId');
    }

    protected function casts(): array
    {
        return [
            'currentPeriodStart' => 'datetime',
            'currentPeriodEnd' => 'datetime',
            'trialEndsAt' => 'datetime',
            'gracePeriodDays' => 'integer',
            'gracePeriodEndsAt' => 'datetime',
            'cancelAtPeriodEnd' => 'boolean',
            'cancelledAt' => 'datetime',
            'lastRenewedAt' => 'datetime',
            'nextBillingDate' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
