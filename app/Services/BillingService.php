<?php

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantRegistration;

class BillingService
{
    public function activatePaid(BillingInvoice $invoice, array $payment): Subscription
    {
        $tenant = Tenant::query()->findOrFail($invoice->tenantId);
        $paidAt = isset($payment['created_at']) ? now()->parse($payment['created_at']) : now();
        $start = $invoice->periodStart ?? $paidAt;
        $end = $invoice->periodEnd ?? $start->copy()->addMonth();
        $grace = $end->copy()->addDays(7);
        $planId = $invoice->gatewayRef && ! str_starts_with((string) $invoice->gatewayRef, 'chg_')
            ? $invoice->gatewayRef
            : Subscription::query()->where('tenantId', $tenant->id)->value('planId');

        $subscription = Subscription::query()->updateOrCreate(
            ['tenantId' => $tenant->id],
            [
                'planId' => $planId,
                'status' => 'ACTIVE',
                'billingCycle' => $invoice->billingCycle,
                'currentPeriodStart' => $start,
                'currentPeriodEnd' => $end,
                'gracePeriodDays' => 7,
                'gracePeriodEndsAt' => $grace,
                'lastRenewedAt' => $paidAt,
                'nextBillingDate' => $end,
                'cancelAtPeriodEnd' => false,
                'cancelledAt' => null,
                'cancellationReason' => null,
            ]
        );

        $wasPaid = $invoice->status === 'PAID';
        $invoice->update([
            'subscriptionId' => $subscription->id,
            'status' => 'PAID',
            'gateway' => 'FLUTTERWAVE',
            'gatewayRef' => (string) ($payment['id'] ?? $payment['tx_ref'] ?? $invoice->invoiceNumber),
            'paidAt' => $paidAt,
            'amount' => $payment['amount'] ?? $invoice->amount,
        ]);

        $this->markShopPaid($tenant);

        if (! $wasPaid) {
            Notification::query()->create([
                'tenantId' => $tenant->id,
                'type' => 'SUBSCRIPTION_RENEWED',
                'title' => 'Subscription Activated',
                'message' => 'Your SalesDock subscription is active. Next billing date: '.$end->copy()->timezone('Africa/Lagos')->format('d M Y').'.',
                'createdAt' => now(),
            ]);
        }

        return $subscription;
    }

    public function activateFree(BillingInvoice $invoice, string $planId): Subscription
    {
        $tenant = Tenant::query()->findOrFail($invoice->tenantId);
        $start = $invoice->periodStart ?? now();
        $end = $invoice->periodEnd ?? $start->copy()->addMonth();
        $paidAt = now();
        $wasPaid = $invoice->status === 'PAID';

        $subscription = Subscription::query()->updateOrCreate(
            ['tenantId' => $tenant->id],
            [
                'planId' => $planId,
                'status' => 'ACTIVE',
                'billingCycle' => $invoice->billingCycle,
                'currentPeriodStart' => $start,
                'currentPeriodEnd' => $end,
                'gracePeriodDays' => 7,
                'gracePeriodEndsAt' => $end->copy()->addDays(7),
                'lastRenewedAt' => $paidAt,
                'nextBillingDate' => $end,
                'cancelAtPeriodEnd' => false,
                'cancelledAt' => null,
                'cancellationReason' => null,
            ]
        );

        $invoice->update([
            'subscriptionId' => $subscription->id,
            'status' => 'PAID',
            'amount' => 0,
            'paidAt' => $paidAt,
            'gatewayRef' => $planId,
        ]);

        $this->markShopPaid($tenant);

        if (! $wasPaid) {
            $plan = \App\Models\Plan::query()->find($planId);
            Notification::query()->create([
                'tenantId' => $tenant->id,
                'type' => 'SUBSCRIPTION_RENEWED',
                'title' => 'Subscription Activated',
                'message' => ($plan?->name ?: 'Your plan').' is active.',
                'createdAt' => now(),
            ]);
        }

        return $subscription;
    }

    private function markShopPaid(Tenant $tenant): void
    {
        TenantRegistration::query()
            ->where('email', $tenant->email)
            ->update([
                'planSelected' => true,
                'planToken' => null,
                'planTokenExp' => null,
            ]);
    }

    public function settleReference(string $txRef, array $payment, SaleRecorder $sales): void
    {
        $invoice = BillingInvoice::query()->where('invoiceNumber', $txRef)->first();
        if ($invoice) {
            $this->activatePaid($invoice, $payment);

            return;
        }

        $order = Order::query()->where('paymentRef', $txRef)->first();
        if ($order) {
            $sales->completePending($order, (string) ($payment['id'] ?? $txRef));
        }
    }
}
