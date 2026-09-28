<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

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

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
        ];
    }
}
