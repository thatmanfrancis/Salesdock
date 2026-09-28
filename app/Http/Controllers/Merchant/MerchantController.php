<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;

abstract class MerchantController extends Controller
{
    protected function tenantId(): string
    {
        $id = $this->user()->tenantId;
        abort_unless($id, 403);

        return $id;
    }

    protected function screen(string $title, array $data = [])
    {
        $data['crumbs'] ??= $title === 'Dashboard'
            ? []
            : [['label' => 'Dashboard', 'href' => route('dashboard')], ['label' => $title]];

        return view('merchant.screen', ['title' => $title] + $data);
    }

    protected function money(mixed $amount): string
    {
        return '₦'.number_format((float) $amount, 2);
    }

    protected function short(mixed $amount, bool $money = false): string
    {
        $number = (float) $amount;
        $sign = $number < 0 ? '-' : '';
        $number = abs($number);
        $body = $number < 1000
            ? number_format($number, $money && floor($number) != $number ? 2 : 0)
            : $this->compact($number);

        return ($money ? '₦' : '').$sign.$body;
    }

    private function compact(float $number): string
    {
        foreach ([1000000000 => 'B', 1000000 => 'M', 1000 => 'K'] as $size => $suffix) {
            if ($number >= $size) {
                $value = $number / $size;
                $places = $value >= 100 || abs($value - round($value)) < 0.05 ? 0 : 1;

                return number_format($value, $places).$suffix;
            }
        }

        return number_format($number, 0);
    }

    protected function link(string $href, string $label): string
    {
        return '<a href="'.e($href).'">'.e($label).'</a>';
    }
}
