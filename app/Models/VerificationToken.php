<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationToken extends Model
{
    protected $table = 'verification_tokens';

    public static $snakeAttributes = false;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'expires' => 'datetime',
        ];
    }
}
