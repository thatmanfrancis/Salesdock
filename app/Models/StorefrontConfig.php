<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontConfig extends Model
{
    protected $table = 'storefront_config';

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

    protected function casts(): array
    {
        return [
            'isPublic' => 'boolean',
            'allowGuestOrder' => 'boolean',
            'updatedAt' => 'datetime',
        ];
    }
}
