<?php

namespace App\Support;

class Permissions
{
    public const KEYS = [
        'canUsePOS', 'canManageProducts', 'canManageOrders', 'canManageCustomers',
        'canManageStaff', 'canManageInventory', 'canManageFinancials', 'canManageSettings',
        'canViewAnalytics', 'canProcessRefunds', 'canManagePromotions', 'canManageSuppliers',
        'canManageBranches', 'canManagePayroll', 'canManageRoles',
        'canOverridePrices', 'canVoidTransactions', 'canApplyDiscounts',
    ];

    private const LEGACY = [
        'pos' => 'canUsePOS',
        'inventory' => 'canManageInventory',
        'orders' => 'canManageOrders',
        'procurement' => 'canManageSuppliers',
        'financials' => 'canManageFinancials',
        'staff' => 'canManageStaff',
        'analytics' => 'canViewAnalytics',
        'settings' => 'canManageSettings',
        'deleteProducts' => 'canManageProducts',
        'manageUsers' => 'canManageRoles',
        'viewCosts' => 'canManageFinancials',
        'refunds' => 'canProcessRefunds',
    ];

    public static function details(): array
    {
        return [
            'canUsePOS' => ['Use POS', 'Open the register and take a sale'],
            'canManageOrders' => ['Orders', 'See the order list and an order'],
            'canManageProducts' => ['Products', 'View, add, and edit products'],
            'canManageCustomers' => ['Customers', 'View and manage customers'],
            'canManageInventory' => ['Inventory', 'See stock and adjust it'],
            'canManageSuppliers' => ['Suppliers', 'View and manage suppliers'],
            'canManagePromotions' => ['Promotions', 'Create and manage promotions'],
            'canApplyDiscounts' => ['Discounts', 'Apply a discount during a sale'],
            'canProcessRefunds' => ['Refunds', 'Submit and manage refunds'],
            'canVoidTransactions' => ['Voids', 'Void a completed sale and restore stock'],
            'canOverridePrices' => ['Price overrides', 'Change a price at the register with a supervisor PIN'],
            'canManageStaff' => ['Staff', 'View and manage staff'],
            'canManagePayroll' => ['Payroll', 'Record and pay payroll'],
            'canViewAnalytics' => ['Analytics', 'Open the analytics page'],
            'canManageFinancials' => ['Financials', 'Open the ledger, expenses, and tax filings'],
            'canManageBranches' => ['Branches', 'View and manage branches'],
            'canManageSettings' => ['Settings', 'Change the shop settings'],
            'canManageRoles' => ['Roles', 'Edit roles and their switches'],
        ];
    }

    public static function defaults(string $name): array
    {
        $all = array_fill_keys(self::KEYS, true);
        $none = array_fill_keys(self::KEYS, false);

        return match ($name) {
            'Owner', 'Manager' => $all,
            'Supervisor' => array_merge($none, [
                'canUsePOS' => true,
                'canManageOrders' => true,
                'canManageCustomers' => true,
                'canManageInventory' => true,
                'canViewAnalytics' => true,
                'canProcessRefunds' => true,
                'canOverridePrices' => true,
                'canVoidTransactions' => true,
                'canApplyDiscounts' => true,
            ]),
            'Cashier' => array_merge($none, ['canUsePOS' => true]),
            'Staff Admin' => array_merge($none, [
                'canManageStaff' => true,
                'canManagePayroll' => true,
            ]),
            default => $none,
        };
    }

    public static function normalize(array $raw): array
    {
        $result = array_fill_keys(self::KEYS, false);

        foreach (self::LEGACY as $old => $new) {
            if (array_key_exists($old, $raw)) {
                $result[$new] = (bool) $raw[$old];
            }
        }

        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $raw)) {
                $result[$key] = (bool) $raw[$key];
            }
        }

        return $result;
    }

    public static function level(string $role): int
    {
        return [
            'CLIENT' => 0,
            'CASHIER' => 1,
            'SUPERVISOR' => 2,
            'MANAGER' => 3,
            'ADMIN' => 4,
            'SUPER_ADMIN' => 5,
        ][$role] ?? 0;
    }
}
