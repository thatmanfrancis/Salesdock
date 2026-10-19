<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\PlatformSetting;
use InvalidArgumentException;

class PaymentManager
{
    public function __construct(
        private Flutterwave $flutterwave,
        private Paystack $paystack,
    ) {}

    public function active(): PaymentGateway
    {
        return $this->driver(PlatformSetting::payment()['provider']);
    }

    public function driver(string $name): PaymentGateway
    {
        return match (strtolower(trim($name))) {
            'flutterwave' => $this->flutterwave,
            'paystack' => $this->paystack,
            default => throw new InvalidArgumentException('Unknown payment provider: '.$name),
        };
    }

    /**
     * Platform share of each storefront card payment as a fraction (0.015 = 1.5%).
     */
    public function platformSplit(): float
    {
        return max(0.0, min(0.5, (float) PlatformSetting::payment()['platform_split']));
    }

    /**
     * Same share as a percentage number for gateways that expect 1.5 instead of 0.015.
     */
    public function platformSplitPercent(): float
    {
        return round($this->platformSplit() * 100, 4);
    }
}
