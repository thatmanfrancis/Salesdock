<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $table = 'support_tickets';

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
            'isAdmin'     => 'boolean',
            'resolvedAt'  => 'datetime',
            'createdAt'   => 'datetime',
            'updatedAt'   => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenantId');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function claimedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimedBy');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportReply::class, 'ticketId')->orderBy('createdAt');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['OPEN', 'IN_PROGRESS'], true);
    }
}
