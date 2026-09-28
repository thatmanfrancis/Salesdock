<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
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
            'approvedAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
