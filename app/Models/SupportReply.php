<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportReply extends Model
{
    protected $table = 'support_replies';

    public static $snakeAttributes = false;

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = null;

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
            'isAdmin'   => 'boolean',
            'createdAt' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticketId');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
