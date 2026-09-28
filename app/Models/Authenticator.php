<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Authenticator extends Model
{
    protected $table = 'authenticators';

    public static $snakeAttributes = false;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    public $incrementing = false;

    protected $primaryKey = ['userId', 'credentialID'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'counter' => 'integer',
            'credentialBackedUp' => 'boolean',
        ];
    }
}
