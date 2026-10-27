<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Tenant extends Model
{
    use SoftDeletes;

    public const POS_PAYMENT_METHODS = ['CASH' => 'Cash', 'CARD' => 'Card', 'TRANSFER' => 'Transfer'];

    /** @return array<string, string> */
    public function enabledPosPaymentMethods(): array
    {
        $methods = array_intersect_key(self::POS_PAYMENT_METHODS, array_flip($this->posPaymentMethods ?? array_keys(self::POS_PAYMENT_METHODS)));
        if ($this->cashlessOnly || $this->requireVerifiedPayments) {
            unset($methods['CASH']);
        }

        return $methods;
    }

    /**
     * Days a deleted shop can still be restored before it is erased for good.
     */
    public const RESTORE_DAYS = 30;

    public const CLOSED_MESSAGE = 'This shop has been closed. Contact SalesDock support to restore it.';

    public const DELETED_AT = 'deletedAt';

    protected $table = 'tenants';

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

    public function purgesAt(): ?Carbon
    {
        return $this->deletedAt?->copy()->addDays(self::RESTORE_DAYS);
    }

    public function users()
    {
        return $this->hasMany(User::class, 'tenantId');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class, 'tenantId');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'tenantId');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'tenantId');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'tenantId');
    }

    protected function casts(): array
    {
        return [
            'taxRate' => 'decimal:2',
            'isActive' => 'boolean',
            'cashlessOnly' => 'boolean',
            'requireVerifiedPayments' => 'boolean',
            'posPaymentMethods' => 'array',
            'restockDrafts' => 'boolean',
            'approvedAt' => 'datetime',
            'profileCompletedAt' => 'datetime',
            'deletedAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
