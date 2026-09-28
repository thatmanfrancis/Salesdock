<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\User;

class Notices
{
    public static function limited(User $user): bool
    {
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return false;
        }

        return ($user->role?->role ?? 'CASHIER') === 'CASHIER';
    }

    public static function types(bool $limited): array
    {
        $sales = [
            'NEW_ORDER' => 'New order',
            'PAYMENT_RECEIVED' => 'Payment received',
        ];
        if ($limited) {
            return $sales;
        }

        return $sales + [
            'LOW_STOCK' => 'Low stock',
            'OUT_OF_STOCK' => 'Out of stock',
            'PO_APPROVED' => 'Purchase order',
            'TAX_REMINDER' => 'Tax reminder',
            'AUDIT_ALERT' => 'Audit alert',
            'SUBSCRIPTION_EXPIRING' => 'Subscription expiring',
            'SUBSCRIPTION_EXPIRED' => 'Subscription expired',
            'SUBSCRIPTION_RENEWED' => 'Subscription renewed',
            'PAYMENT_FAILED' => 'Payment failed',
            'SYSTEM' => 'System',
        ];
    }

    public static function scope(User $user)
    {
        $query = Notification::query()->where('tenantId', $user->tenantId);
        if (self::limited($user)) {
            $query->whereIn('type', array_keys(self::types(true)));
        }

        return $query;
    }

    public static function preview(User $user): array
    {
        $query = self::scope($user);

        return [
            'items' => (clone $query)->latest('createdAt')->limit(10)->get(),
            'unread' => (clone $query)->where('isRead', false)->count(),
            'labels' => self::types(self::limited($user)),
        ];
    }
}
