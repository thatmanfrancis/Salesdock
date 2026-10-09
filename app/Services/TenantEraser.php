<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantEraser
{
    /**
     * Tables keyed by tenantId, ordered so restrict foreign keys are cleared before their parents.
     *
     * @var list<string>
     */
    private const TABLES = [
        'sales_ledger',
        'refunds',
        'transactions',
        'holds',
        'orders',
        'purchase_orders',
        'staff_payroll',
        'stock_movements',
        'promotions',
        'product_variants',
        'products',
        'suppliers',
        'customers',
        'support_tickets',
        'away_check_ins',
        'payment_links',
        'virtual_accounts',
        'tax_filings',
        'expense_items',
        'expenses',
        'financial_goals',
        'notifications',
        'activity_logs',
        'audit_logs',
        'billing_invoices',
        'subscriptions',
        'vat_settings',
        'storefront_config',
        'users',
        'roles',
        'branches',
    ];

    /**
     * Turn a shop off and sign everyone out, keeping its data so it can be restored.
     */
    public function close(Tenant $tenant, ?string $deletedBy = null): void
    {
        DB::transaction(function () use ($tenant, $deletedBy) {
            $tenant->forceFill(['deletedBy' => $deletedBy])->save();
            $tenant->delete();
            $this->endSessions($tenant->id);
        });
    }

    public function restore(Tenant $tenant): void
    {
        $tenant->deletedBy = null;
        $tenant->restore();
    }

    /**
     * Permanently remove a shop and every record tied to it.
     */
    public function erase(Tenant $tenant): void
    {
        $id = $tenant->id;
        $emails = DB::table('users')->where('tenantId', $id)->pluck('email')
            ->push($tenant->email)
            ->filter()
            ->map(fn ($email) => strtolower((string) $email))
            ->unique()
            ->values();

        DB::transaction(function () use ($tenant, $id, $emails) {
            $this->endSessions($id);

            DB::table('order_items')->whereIn('orderId', fn ($query) => $query->select('id')->from('orders')->where('tenantId', $id))->delete();
            DB::table('purchase_order_items')->whereIn('purchaseOrderId', fn ($query) => $query->select('id')->from('purchase_orders')->where('tenantId', $id))->delete();
            if (Schema::hasTable('support_replies')) {
                DB::table('support_replies')->whereIn('ticketId', fn ($query) => $query->select('id')->from('support_tickets')->where('tenantId', $id))->delete();
            }

            foreach (self::TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('tenantId', $id)->delete();
                }
            }

            $tenant->forceDelete();

            if ($emails->isNotEmpty()) {
                TenantRegistration::query()->whereIn(DB::raw('lower(email)'), $emails->all())->delete();
            }
        });
    }

    private function endSessions(string $tenantId): void
    {
        $table = (string) config('session.table', 'sessions');
        if (config('session.driver') !== 'database' || ! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->whereIn('user_id', fn ($query) => $query->select('id')->from('users')->where('tenantId', $tenantId))
            ->delete();
    }
}
