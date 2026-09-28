<?php

namespace App\Support;

class Nav
{
    public static function items(string $role, array $permissions, ?string $roleName = null, array $features = []): array
    {
        if ($role === 'SUPER_ADMIN') {
            return self::map(self::platform());
        }

        if ($role === 'CLIENT') {
            return self::map([
                ['/account', 'My Orders'],
                ['/account/loyalty', 'Loyalty'],
                ['/profile', 'Profile'],
            ]);
        }

        $permissions = self::granted($roleName, $permissions);
        $visible = [];
        foreach (self::store() as $item) {
            if (self::visible($role, $item, $permissions, $roleName) && PlanFeatures::covers($item['href'], $features)) {
                $visible[] = [$item['href'], $item['label']];
            }
        }

        return self::map($visible);
    }

    public static function allows(string $role, string $path, array $permissions = [], ?string $roleName = null, array $features = [], bool $checkPlan = true): bool
    {
        $path = '/'.ltrim($path, '/');
        if ($path === '/') {
            return true;
        }

        if ($role === 'CLIENT' || $role === 'SUPER_ADMIN') {
            return self::matchesPrefix($role, $path);
        }

        $permissions = self::granted($roleName, $permissions);
        $match = null;
        $length = 0;
        foreach (self::store() as $item) {
            $href = $item['href'];
            if (($path === $href || str_starts_with($path, $href.'/')) && strlen($href) >= $length) {
                $length = strlen($href);
                $match = $item;
            }
        }

        $open = ! $checkPlan || PlanFeatures::covers($path, $features);
        if ($match) {
            return self::visible($role, $match, $permissions, $roleName) && $open;
        }

        return self::matchesPrefix($role, $path) && $open;
    }

    private static function granted(?string $roleName, array $permissions): array
    {
        if ($roleName === 'Owner') {
            return array_fill_keys(Permissions::KEYS, true);
        }

        return $permissions;
    }

    private static function visible(string $role, array $item, array $permissions, ?string $roleName = null): bool
    {
        if ($item['href'] === '/audit-logs' && $roleName === 'Staff Admin') {
            return false;
        }

        if ($item['permission']) {
            if (empty($permissions[$item['permission']])) {
                return false;
            }

            if (in_array($item['href'], ['/branches', '/settings', '/roles'], true) && $roleName === 'Staff Admin') {
                return false;
            }

            if (in_array($item['href'], ['/financials/ledger', '/financials/tax-filings', '/expenses', '/analytics', '/staff', '/payroll', '/branches', '/settings', '/roles'], true) && ! in_array($role, $item['roles'], true)) {
                return false;
            }

            return true;
        }

        return in_array($role, $item['roles'], true);
    }

    private static function matchesPrefix(string $role, string $path): bool
    {
        $prefixes = [
            'CLIENT' => ['/account', '/profile'],
            'CASHIER'    => ['/dashboard', '/pos', '/products', '/orders', '/notifications', '/support', '/profile', '/billing'],
            'SUPERVISOR' => ['/pos', '/dashboard', '/orders', '/products', '/customers', '/refunds', '/inventory', '/suppliers', '/promotions', '/staff', '/payroll', '/activity-logs', '/notifications', '/support', '/profile', '/billing'],
            'MANAGER'    => ['/dashboard', '/orders', '/products', '/customers', '/inventory', '/suppliers', '/promotions', '/refunds', '/analytics', '/staff', '/branches', '/payroll', '/roles', '/settings', '/audit-logs', '/activity-logs', '/financials', '/expenses', '/notifications', '/support', '/profile', '/billing'],
            'ADMIN'      => ['/pos', '/dashboard', '/orders', '/products', '/customers', '/inventory', '/suppliers', '/promotions', '/refunds', '/analytics', '/staff', '/payroll', '/branches', '/roles', '/settings', '/audit-logs', '/activity-logs', '/financials', '/expenses', '/notifications', '/support', '/profile', '/billing', '/admin/registrations'],
            'SUPER_ADMIN' => ['/admin', '/profile', '/notifications', '/dashboard', '/analytics', '/billing'],
        ][$role] ?? [];

        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private static function map(array $pairs): array
    {
        return array_map(fn ($pair) => [
            'href' => url($pair[0]),
            'path' => $pair[0],
            'label' => $pair[1],
            'icon' => $pair[0],
        ], $pairs);
    }

    private static function store(): array
    {
        $all = ['CASHIER', 'SUPERVISOR', 'MANAGER', 'ADMIN', 'SUPER_ADMIN'];
        $supervisor = ['SUPERVISOR', 'MANAGER', 'ADMIN', 'SUPER_ADMIN'];
        $manager = ['MANAGER', 'ADMIN', 'SUPER_ADMIN'];

        return [
            ['href' => '/dashboard', 'label' => 'Dashboard', 'roles' => $all, 'permission' => null],
            ['href' => '/pos', 'label' => 'POS Terminal', 'roles' => ['CASHIER', 'SUPERVISOR', 'ADMIN'], 'permission' => 'canUsePOS'],
            ['href' => '/orders', 'label' => 'Orders', 'roles' => $all, 'permission' => 'canManageOrders'],
            ['href' => '/products', 'label' => 'Products', 'roles' => $all, 'permission' => 'canManageProducts'],
            ['href' => '/customers', 'label' => 'Customers', 'roles' => $supervisor, 'permission' => 'canManageCustomers'],
            ['href' => '/refunds', 'label' => 'Refunds', 'roles' => $supervisor, 'permission' => 'canProcessRefunds'],
            ['href' => '/inventory', 'label' => 'Inventory', 'roles' => $supervisor, 'permission' => 'canManageInventory'],
            ['href' => '/suppliers', 'label' => 'Suppliers', 'roles' => $supervisor, 'permission' => 'canManageSuppliers'],
            ['href' => '/promotions', 'label' => 'Promotions', 'roles' => $supervisor, 'permission' => 'canManagePromotions'],
            ['href' => '/financials/ledger', 'label' => 'Financials', 'roles' => $manager, 'permission' => 'canManageFinancials'],
            ['href' => '/financials/tax-filings', 'label' => 'Tax Filings', 'roles' => ['ADMIN'], 'permission' => 'canManageFinancials'],
            ['href' => '/expenses', 'label' => 'Expenses', 'roles' => $manager, 'permission' => 'canManageFinancials'],
            ['href' => '/analytics', 'label' => 'Analytics', 'roles' => $supervisor, 'permission' => 'canViewAnalytics'],
            ['href' => '/staff', 'label' => 'Staff', 'roles' => $supervisor, 'permission' => 'canManageStaff'],
            ['href' => '/payroll', 'label' => 'Payroll', 'roles' => $supervisor, 'permission' => 'canManagePayroll'],
            ['href' => '/branches', 'label' => 'Branches', 'roles' => $manager, 'permission' => 'canManageBranches'],
            ['href' => '/settings', 'label' => 'Settings', 'roles' => $manager, 'permission' => 'canManageSettings'],
            ['href' => '/audit-logs', 'label' => 'Audit Logs', 'roles' => $manager, 'permission' => null],
            ['href' => '/activity-logs', 'label' => 'Activity Logs', 'roles' => $supervisor, 'permission' => null],
            ['href' => '/roles', 'label' => 'Roles', 'roles' => $manager, 'permission' => 'canManageRoles'],
            ['href' => '/notifications', 'label' => 'Notifications', 'roles' => $all, 'permission' => null],
            ['href' => '/support', 'label' => 'Support', 'roles' => $all, 'permission' => null],
            ['href' => '/profile', 'label' => 'Profile', 'roles' => $all, 'permission' => null],
            ['href' => '/billing', 'label' => 'Billing', 'roles' => $all, 'permission' => null],
        ];
    }

    private static function platform(): array
    {
        return [
            ['/admin', 'Overview'],
            ['/admin/tenants', 'Tenants'],
            ['/admin/registrations', 'Registrations'],
            ['/admin/subscriptions', 'Subscriptions'],
            ['/admin/billing', 'Billing'],
            ['/admin/revenue', 'Revenue'],
            ['/admin/analytics', 'Analytics'],
            ['/admin/tax-filings', 'Tax Filings'],
            ['/admin/users', 'Users'],
            ['/admin/customers', 'Customers'],
            ['/admin/catalogue', 'Catalogue'],
            ['/admin/audit-logs', 'Audit Logs'],
            ['/admin/activity-logs', 'Activity Logs'],
            ['/admin/webhooks', 'Webhooks'],
            ['/admin/support', 'Support'],
            ['/profile', 'Profile'],
        ];
    }
}
