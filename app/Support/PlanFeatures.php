<?php

namespace App\Support;

use App\Models\Plan;

class PlanFeatures
{
    private const GATES = [
        '/pos' => ['POS Terminal'],
        '/orders' => ['Order Management'],
        '/products' => ['Basic Inventory', 'Advanced Inventory'],
        '/customers' => ['Customer Records', 'Loyalty Program'],
        '/refunds' => ['Refund Management'],
        '/inventory' => ['Basic Inventory', 'Advanced Inventory', 'Purchase Orders'],
        '/suppliers' => ['Supplier Management'],
        '/promotions' => ['Promotions & Discounts'],
        '/financials/tax-filings' => ['Tax Filings'],
        '/financials/ledger' => ['Advanced Analytics'],
        '/expenses' => ['Advanced Analytics'],
        '/analytics' => ['Analytics Dashboard', 'Advanced Analytics'],
        '/staff' => ['Staff Management', 'Unlimited Users'],
        '/payroll' => ['Payroll Management'],
        '/branches' => ['Multi-branch Support', 'Unlimited Branches'],
        '/audit-logs' => ['Audit Logs'],
        '/activity-logs' => ['Activity Logs'],
        '/roles' => ['Role Management'],
    ];

    public static function covers(string $path, array $features): bool
    {
        $path = '/'.ltrim($path, '/');
        $need = null;
        $length = 0;
        foreach (self::GATES as $href => $any) {
            if (($path === $href || str_starts_with($path, $href.'/')) && strlen($href) > $length) {
                $length = strlen($href);
                $need = $any;
            }
        }
        if ($need === null) {
            return true;
        }

        return self::includes($features, $need);
    }

    public static function includes(array $features, array $any): bool
    {
        return array_intersect($any, self::expand($features)) !== [];
    }

    public static function expand(array $features): array
    {
        $out = [];
        $seen = [];
        $walk = function (array $list) use (&$walk, &$out, &$seen): void {
            foreach ($list as $feature) {
                if (! is_string($feature) || isset($seen[$feature])) {
                    continue;
                }
                $seen[$feature] = true;
                if (str_starts_with($feature, 'Everything in ')) {
                    $walk(self::named(substr($feature, strlen('Everything in '))));
                    continue;
                }
                $out[] = $feature;
            }
        };
        $walk($features);

        return array_values(array_unique($out));
    }

    private static function named(string $name): array
    {
        static $plans = null;
        if ($plans === null) {
            $plans = [];
            foreach (Plan::query()->get(['name', 'features']) as $plan) {
                $plans[$plan->name] = is_array($plan->features) ? $plan->features : [];
            }
        }

        return $plans[$name] ?? [];
    }
}
