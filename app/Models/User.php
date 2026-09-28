<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

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

    protected $hidden = [
        'passwordHash',
        'pin',
        'twoFaSecret',
    ];

    protected $rememberTokenName = '';

    public function getAuthPassword(): string
    {
        return (string) $this->passwordHash;
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'roleId');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branchId');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenantId');
    }

    public function homePath(): string
    {
        $role = $this->role?->role;

        if ($this->isSuperAdmin || $role === 'SUPER_ADMIN') {
            return route('admin.home');
        }

        return route('dashboard');
    }

    protected function casts(): array
    {
        return [
            'pinIsDefault' => 'boolean',
            'isSuperAdmin' => 'boolean',
            'isActive' => 'boolean',
            'twoFaEnabled' => 'boolean',
            'lastLoginAt' => 'datetime',
            'shiftStartedAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
