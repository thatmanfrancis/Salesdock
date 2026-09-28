<?php

namespace App\Support;

class Totp
{
    public static function secret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        $bytes = random_bytes(20);
        $buffer = 0;
        $bits = 0;
        foreach (str_split($bytes) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $secret .= $alphabet[($buffer >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $secret .= $alphabet[($buffer << (5 - $bits)) & 31];
        }

        return $secret;
    }

    public static function uri(string $secret, string $email): string
    {
        $label = rawurlencode('SalesDock:'.$email);

        return 'otpauth://totp/'.$label.'?secret='.$secret.'&issuer='.rawurlencode('SalesDock').'&digits=6&period=30';
    }

    public static function qr(string $uri): string
    {
        return \App\Support\Qr::svg($uri);
    }

    public static function verify(?string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if (! $secret || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $slice = (int) floor(time() / 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::code($secret, $slice + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private static function code(string $secret, int $slice): string
    {
        $key = self::base32Decode($secret);
        $time = pack('N*', 0, $slice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $binary = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $buffer = 0;
        $bits = 0;
        $output = '';

        foreach (str_split($secret) as $char) {
            $value = strpos($alphabet, $char);
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $output;
    }
}
