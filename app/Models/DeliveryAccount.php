<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryAccount extends Model
{
    protected $table = 'delivery_accounts';

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
            'userId' => 'encrypted',
            'password' => 'encrypted',
            'secretKey' => 'encrypted',
            'authToken' => 'encrypted',
            'tokenExpiresAt' => 'datetime',
            'enabled' => 'boolean',
            'autoBook' => 'boolean',
            'defaultWeightKg' => 'decimal:2',
            'packagingWeightKg' => 'decimal:2',
            'lastTestedAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'deliveryAccountId');
    }

    public function connected(): bool
    {
        return $this->enabled && filled($this->userId) && filled($this->password);
    }
}
