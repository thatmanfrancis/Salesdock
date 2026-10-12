<?php

namespace App\Support;

use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * The post-payment modal: the payment result, then a short tour of only the pages the paid plan
 * (and the viewer's role) actually opens, so nobody is walked through features they did not buy.
 */
class PlanTour
{
    public const SESSION_KEY = 'payment_result';

    /**
     * Tour stops in the order a new shop usually needs them. Each only shows when its page is in the nav.
     *
     * @var list<array{path: string, title: string, body: string, cta: string}>
     */
    private const STOPS = [
        ['path' => '/products', 'title' => 'Add your products', 'body' => 'Add items one by one, import a CSV, or start from the SalesDock catalogue. Set prices, cost and opening stock as you go.', 'cta' => 'Open Products'],
        ['path' => '/pos', 'title' => 'Make your first sale', 'body' => 'The POS terminal lets you search or scan items, take cash, transfer or card, and print a receipt in seconds.', 'cta' => 'Open POS'],
        ['path' => '/orders', 'title' => 'Track every order', 'body' => 'Every sale and online order lands here. Open one to reprint a receipt, see who sold it, or void it with approval.', 'cta' => 'Open Orders'],
        ['path' => '/inventory', 'title' => 'Keep stock in check', 'body' => 'See what is running low, adjust counts after a stock-take, and review every stock movement.', 'cta' => 'Open Inventory'],
        ['path' => '/customers', 'title' => 'Know your customers', 'body' => 'Save customer details at checkout and see what each customer buys and how much they spend.', 'cta' => 'Open Customers'],
        ['path' => '/branches', 'title' => 'Run more than one branch', 'body' => 'Add branches, keep separate stock for each one, and move stock between them when one runs low.', 'cta' => 'Open Branches'],
        ['path' => '/staff', 'title' => 'Bring in your team', 'body' => 'Invite cashiers and managers with their own logins and PINs, and control exactly what each role can do.', 'cta' => 'Open Staff'],
        ['path' => '/suppliers', 'title' => 'Restock from suppliers', 'body' => 'Keep supplier contacts, raise purchase orders, and receive deliveries straight into stock.', 'cta' => 'Open Suppliers'],
        ['path' => '/promotions', 'title' => 'Run promotions', 'body' => 'Create discounts and promo codes that apply automatically at the POS and on your online store.', 'cta' => 'Open Promotions'],
        ['path' => '/analytics', 'title' => 'See how you are doing', 'body' => 'Sales trends, best sellers, and staff performance, with reports you can download as PDF.', 'cta' => 'Open Analytics'],
        ['path' => '/financials/ledger', 'title' => 'Track the money', 'body' => 'Your ledger, profit and loss, and expenses in one place, ready for your accountant.', 'cta' => 'Open Financials'],
        ['path' => '/payroll', 'title' => 'Pay your staff', 'body' => 'Record salaries, bonuses and deductions, and print payslips for each pay run.', 'cta' => 'Open Payroll'],
    ];

    /**
     * Pulls the one-time payment result from the session and builds the modal content, or null when there is none.
     *
     * @param  list<array{path: string, href: string, label: string}>  $nav
     * @return array{state: string, message: ?string, invoice: ?BillingInvoice, plan: ?Plan, limits: list<string>, steps: list<array<string, string>>, retryUrl: ?string}|null
     */
    public static function pull(?string $tenantId, array $nav, bool $opensStorefront): ?array
    {
        $result = session()->pull(self::SESSION_KEY);
        if (! is_array($result) || ! $tenantId) {
            return null;
        }

        $invoice = isset($result['invoice'])
            ? BillingInvoice::query()->whereKey($result['invoice'])->where('tenantId', $tenantId)->first()
            : null;
        $state = in_array($result['state'] ?? null, ['success', 'failed', 'cancelled'], true) ? $result['state'] : 'failed';

        $plan = null;
        if ($state === 'success') {
            $plan = Subscription::query()->with('plan')->where('tenantId', $tenantId)->first()?->plan;
        }

        return [
            'state' => $state,
            'message' => isset($result['message']) ? (string) $result['message'] : null,
            'invoice' => $invoice,
            'plan' => $plan,
            'limits' => $plan ? self::limits($plan) : [],
            'steps' => $state === 'success' ? self::steps($nav, $opensStorefront) : [],
            'retryUrl' => $invoice && $invoice->status === 'PENDING' ? route('billing.pay', $invoice) : null,
        ];
    }

    /**
     * @param  list<array{path: string, href: string, label: string}>  $nav
     * @return list<array{title: string, body: string, cta: string, href: string, icon: string}>
     */
    public static function steps(array $nav, bool $opensStorefront): array
    {
        $open = [];
        foreach ($nav as $item) {
            $open[$item['path']] = $item['href'];
        }

        $steps = [];
        foreach (self::STOPS as $stop) {
            if (isset($open[$stop['path']])) {
                $steps[] = $stop + ['href' => $open[$stop['path']], 'icon' => $stop['path']];
            }
        }

        if ($opensStorefront && isset($open['/settings'])) {
            $steps[] = [
                'path' => '/settings',
                'title' => 'Sell online',
                'body' => 'Switch on your online store, pick your colours, and share the link. Orders and card payments come straight into SalesDock.',
                'cta' => 'Set up your store',
                'href' => route('settings', ['section' => 'storefront']),
                'icon' => '/settings',
            ];
        }

        return array_map(fn (array $step) => array_diff_key($step, ['path' => true]), $steps);
    }

    /**
     * @return list<string>
     */
    private static function limits(Plan $plan): array
    {
        $count = fn (?int $value, string $one, string $many, int $unlimited) => $value === null ? null
            : ($value >= $unlimited ? 'Unlimited '.$many : number_format($value).' '.($value === 1 ? $one : $many));

        return array_values(array_filter([
            $count($plan->maxBranches, 'branch', 'branches', 999),
            $count($plan->maxUsers, 'user', 'users', 999),
            $count($plan->maxProducts, 'product', 'products', 999999),
        ]));
    }
}
