<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantRegistration extends Model
{
    protected $table = 'tenant_registrations';

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
            'emailVerified' => 'boolean',
            'emailVerifiedAt' => 'datetime',
            'verifyTokenExp' => 'datetime',
            'reviewedAt' => 'datetime',
            'planTokenExp' => 'datetime',
            'planSelected' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
