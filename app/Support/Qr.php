<?php

namespace App\Support;

use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class Qr
{
    public static function svg(string $text): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'scale' => 5,
        ]);

        $svg = (new QRCode($options))->render($text);

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg) ?? $svg;
    }
}
