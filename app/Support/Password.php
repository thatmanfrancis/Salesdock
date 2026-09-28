<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

class Password
{
    public static function check(string $plain, ?string $hashed): bool
    {
        if ($hashed === null || $hashed === '') {
            return false;
        }

        try {
            return Hash::check($plain, self::normalize($hashed));
        } catch (\RuntimeException) {
            return false;
        }
    }

    public static function normalize(string $hashed): string
    {
        if (preg_match('/^\$2[ab]\$/', $hashed) === 1) {
            return '$2y$'.substr($hashed, 4);
        }

        return $hashed;
    }
}
