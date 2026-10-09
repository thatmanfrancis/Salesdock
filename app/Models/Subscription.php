<?php

namespace App\Models;

use App\Support\PlanFeatures;
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

    public function onTrial(): bool
    {
        return $this->status === 'TRIALING' && $this->trialEndsAt?->isFuture();
    }

    /**
     * The trial or paid period is over and the shop must pick a paid plan to continue.
     */
    public function hasEnded(): bool
    {
        if ($this->status === 'EXPIRED') {
            return true;
        }

        return $this->status === 'TRIALING' && ! $this->trialEndsAt?->isFuture();
    }

    /**
     * Features this shop can use right now. A running trial opens everything; an ended one opens nothing.
     *
     * @return list<string>
     */
    public function features(): array
    {
        if ($this->onTrial()) {
            return PlanFeatures::everything();
        }
        if ($this->hasEnded()) {
            return [];
        }

        return is_array($this->plan?->features) ? $this->plan->features : [];
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
