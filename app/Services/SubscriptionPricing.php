<?php

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * @phpstan-type Pricing array{planId: string, cycle: string, regularAmount: string, introAmount: string, introPeriods: int, introRemaining: int}
 */
class SubscriptionPricing
{
    /** @return Pricing */
    public static function forInvoice(BillingInvoice $invoice): array
    {
        return $invoice->billingMetadata['pricing'] ?? ['planId' => $invoice->billingMetadata['planId'] ?? $invoice->gatewayRef,
            'cycle' => $invoice->billingCycle, 'regularAmount' => (string) $invoice->amount,
            'introAmount' => (string) $invoice->amount, 'introPeriods' => 0, 'introRemaining' => 0];
    }

    public static function regularAmount(Plan $plan, string $cycle): float
    {
        return (float) match ($cycle) {
            'QUARTERLY' => $plan->quarterlyPrice,
            'ANNUALLY' => $plan->annualPrice,
            default => $plan->monthlyPrice,
        };
    }

    /** @return array{planId: string, cycle: string, regularAmount: string, introAmount: string, introPeriods: int, introRemaining: int} */
    public static function quote(Plan $plan, string $cycle, string $tenantId): array
    {
        $regular = self::regularAmount($plan, $cycle);
        $subscription = Subscription::query()->where('tenantId', $tenantId)->first();
        $existing = $subscription?->billingPricing;
        if ($subscription?->planId === $plan->id && $subscription->billingCycle === $cycle
            && $existing && abs((float) $existing['regularAmount'] - $regular) < 0.009) {
            return $existing;
        }
        $offer = $plan->introductoryPricing[$cycle] ?? [];
        $eligible = ! BillingInvoice::query()->where('tenantId', $tenantId)->where('status', 'PAID')->exists();
        $intro = (float) ($offer['amount'] ?? 0);
        $periods = $eligible && $intro > 0 && $intro < $regular ? (int) ($offer['periods'] ?? 0) : 0;

        return ['planId' => $plan->id, 'cycle' => $cycle, 'regularAmount' => number_format($regular, 2, '.', ''),
            'introAmount' => number_format($periods > 0 ? $intro : $regular, 2, '.', ''),
            'introPeriods' => $periods, 'introRemaining' => $periods];
    }

    /** @param Pricing $pricing */
    public static function amount(array $pricing): float
    {
        return (float) ($pricing['introRemaining'] > 0 ? $pricing['introAmount'] : $pricing['regularAmount']);
    }

    /**
     * @param  Pricing  $pricing
     * @return Pricing
     */
    public static function afterPayment(array $pricing): array
    {
        $pricing['introRemaining'] = max(0, (int) $pricing['introRemaining'] - 1);

        return $pricing;
    }

    /** @param Pricing $pricing */
    public static function description(array $pricing, bool $includesCurrentPayment = true): string
    {
        $interval = match ($pricing['cycle']) {
            'QUARTERLY' => 'three-month period', 'ANNUALLY' => 'year', default => 'month',
        };
        $regular = 'NGN '.number_format((float) $pricing['regularAmount'], 2).' per '.$interval;
        if ($pricing['introRemaining'] > 0) {
            return 'NGN '.number_format((float) $pricing['introAmount'], 2).' per '.$interval.' for the next '.$pricing['introRemaining'].' paid billing period(s)'.($includesCurrentPayment ? ', including this payment' : '').', then '.$regular.'.';
        }

        return $regular.'.';
    }

    public static function matchesAuthorisation(Subscription $subscription): bool
    {
        $pricing = $subscription->billingPricing;
        $agreed = $subscription->recurringConsent['pricing'] ?? null;
        if (! $agreed) {
            return ! $pricing || ((int) $pricing['introPeriods'] === 0
                && $pricing['planId'] === $subscription->planId && $pricing['cycle'] === $subscription->billingCycle);
        }
        if (! $pricing) {
            return false;
        }
        foreach (['planId', 'cycle', 'regularAmount', 'introAmount', 'introPeriods'] as $field) {
            if ($pricing[$field] !== $agreed[$field]) {
                return false;
            }
        }

        return $pricing['introRemaining'] >= 0 && $pricing['introRemaining'] <= $agreed['introRemaining'];
    }
}
