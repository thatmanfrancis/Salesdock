<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $table = 'branches';

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
        return $this->hasMany(User::class, 'branchId');
    }

    protected function casts(): array
    {
        return [
            'isMain' => 'boolean',
            'isActive' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
