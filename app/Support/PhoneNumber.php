<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalise a Nigerian (or already international) mobile number to E.164 (+234…).
     */
    public static function e164(?string $raw, string $defaultCountry = '234'): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = $defaultCountry.substr($digits, 1);
        }

        if (strlen($digits) === 10 && (str_starts_with($digits, '7') || str_starts_with($digits, '8') || str_starts_with($digits, '9'))) {
            $digits = $defaultCountry.$digits;
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return '+'.$digits;
    }

    /**
     * Digits only for wa.me links (no plus).
     */
    public static function waMe(?string $raw): ?string
    {
        $e164 = self::e164($raw);
        if ($e164 === null) {
            return null;
        }

        return ltrim($e164, '+');
    }
}
