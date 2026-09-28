<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffPayroll extends Model
{
    protected $table = 'staff_payroll';

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

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    protected function casts(): array
    {
        return [
            'baseSalary' => 'decimal:2',
            'bonus' => 'decimal:2',
            'deductions' => 'decimal:2',
            'netPay' => 'decimal:2',
            'isPaid' => 'boolean',
            'paidAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
