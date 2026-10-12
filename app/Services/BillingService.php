<?php

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantRegistration;
use Illuminate\Support\Facades\Log;

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

    /**
     * Applies a Flutterwave-verified payment to the invoice or storefront order it belongs to.
     * Returns what was settled, or null when the payment does not match (wrong reference, amount, currency or status).
     */
    public function settlePayment(array $payment, Flutterwave $flutterwave, SaleRecorder $sales): ?string
    {
        $reference = Flutterwave::baseReference((string) ($payment['tx_ref'] ?? ''));
        if ($reference === '') {
            return null;
        }

        $invoice = BillingInvoice::query()->where('invoiceNumber', $reference)->first();
        if ($invoice) {
            if ($invoice->status !== 'PAID' && ! $flutterwave->confirms($payment, $reference, (float) $invoice->amount)) {
                Log::warning('Flutterwave payment did not match invoice', ['invoice' => $reference, 'paid' => $payment['amount'] ?? null, 'status' => $payment['status'] ?? null]);

                return null;
            }
            $this->activatePaid($invoice, $payment);

            return 'invoice';
        }

        $order = Order::query()->where('paymentRef', $reference)->first();
        if ($order) {
            if ($order->status === 'PENDING' && ! $flutterwave->confirms($payment, $reference, (float) $order->netAmount)) {
                Log::warning('Flutterwave payment did not match order', ['order' => $reference, 'paid' => $payment['amount'] ?? null, 'status' => $payment['status'] ?? null]);

                return null;
            }
            $sales->completePending($order, (string) ($payment['id'] ?? $reference));

            return 'order';
        }

        return null;
    }
}
