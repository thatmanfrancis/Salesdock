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

    protected $hidden = ['recurringAuthorization'];

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

    public function trialDaysRemaining(): ?int
    {
        if (! $this->onTrial()) {
            return null;
        }

        return max(1, (int) ceil((float) now()->diffInDays($this->trialEndsAt)));
    }

    public function hasEndedFreeTrial(): bool
    {
        if (! $this->trialEndsAt || $this->trialEndsAt->isFuture()) {
            return false;
        }

        return $this->status === 'TRIALING'
            || ($this->status === 'EXPIRED' && $this->currentPeriodEnd?->equalTo($this->trialEndsAt));
    }

    public function hasPlanAccess(): bool
    {
        return $this->onTrial() || in_array($this->status, ['ACTIVE', 'PAST_DUE'], true);
    }

    public function usesBasicAccess(): bool
    {
        return in_array($this->status, ['CANCELLED', 'EXPIRED'], true)
            || ($this->status === 'TRIALING' && ! $this->onTrial());
    }

    /**
     * Whether the trial or paid period has ended. Basic access may remain available.
     */
    public function hasEnded(): bool
    {
        if ($this->status === 'EXPIRED') {
            return true;
        }

        return $this->status === 'TRIALING' && ! $this->trialEndsAt?->isFuture();
    }

    /**
     * A running trial opens every feature. Ended paid access falls back to Basic; suspension blocks access.
     *
     * @return list<string>
     */
    public function features(): array
    {
        if ($this->onTrial()) {
            return PlanFeatures::everything();
        }
        if (! $this->hasPlanAccess()) {
            return $this->usesBasicAccess() ? PlanFeatures::basic() : [];
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
            'autoRenew' => 'boolean',
            'recurringAuthorization' => 'encrypted:array',
            'recurringConsent' => 'array',
            'recurringAmount' => 'decimal:2',
            'billingPricing' => 'array',
            'recurringRemindedAt' => 'datetime',
            'autoRenewCancelledAt' => 'datetime',
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
