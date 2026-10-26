<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\AuditLog;
use App\Models\BillingInvoice;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantRegistration;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
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
            'gateway' => strtoupper((string) ($payment['gateway'] ?? 'CARD')),
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
     * Applies a gateway-verified payment to the invoice or storefront order it belongs to.
     * Returns what was settled, or null when the payment does not match (wrong reference, amount, currency or status).
     */
    public function settlePayment(array $payment, PaymentGateway $gateway, SaleRecorder $sales): ?string
    {
        $reference = $gateway->baseReference((string) ($payment['tx_ref'] ?? ''));
        if ($reference === '') {
            return null;
        }

        $invoice = BillingInvoice::query()->where('invoiceNumber', $reference)->first();
        if ($invoice) {
            if ($invoice->status !== 'PAID' && ! $gateway->confirms($payment, $reference, (float) $invoice->amount)) {
                Log::warning($gateway->label().' payment did not match invoice', ['invoice' => $reference, 'paid' => $payment['amount'] ?? null, 'status' => $payment['status'] ?? null]);

                return null;
            }
            $this->activatePaid($invoice, $payment);

            return 'invoice';
        }

        $order = Order::query()->where('paymentRef', $reference)->first();
        if ($order) {
            return DB::transaction(function () use ($order, $payment, $gateway, $sales, $reference): ?string {
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);
                $transaction = Transaction::query()->where('orderId', $order->id)->first();
                $metadata = $transaction?->metadata ?? [];
                if (! $gateway->confirms($payment, $reference, (float) $order->netAmount)
                    || ($order->paymentVerificationRequired && (
                        strtoupper($gateway->name()) !== $transaction?->gateway
                        || ! in_array($payment['tx_ref'] ?? null, array_filter(array_merge(
                            [$metadata['providerReference'] ?? null], $metadata['previousReferences'] ?? []
                        )), true)
                        || abs((float) $payment['amount'] - (float) $order->netAmount) > 0.009
                    ))) {
                    Log::warning('Payment did not match order', ['order' => $reference, 'gateway' => $gateway->name()]);

                    return null;
                }
                $gatewayRef = (string) ($payment['id'] ?? $reference);
                if ($order->status === 'COMPLETED' && $transaction?->gatewayRef === $gatewayRef) {
                    return 'order';
                }
                if (! in_array($order->status, ['PENDING', 'RESERVED'], true)) {
                    if (! in_array($gatewayRef, $metadata['reviewPaymentIds'] ?? [], true)) {
                        $metadata['needsReview'] = true;
                        $metadata['reviewPaymentIds'][] = $gatewayRef;
                        $metadata['reviewReason'] = 'Payment received for a '.$order->status.' sale. Reconcile before refunding or fulfilling.';
                        $transaction?->forceFill(['metadata' => $metadata])->save();
                        AuditLog::query()->create([
                            'tenantId' => $order->tenantId, 'userId' => $order->cashierId,
                            'action' => 'PAYMENT_RECONCILIATION_REQUIRED',
                            'details' => ['orderId' => $order->id, 'branchId' => $order->branchId,
                                'gateway' => $gateway->name(), 'gatewayRef' => $gatewayRef,
                                'amount' => $payment['amount'], 'reason' => $metadata['reviewReason']],
                            'timestamp' => now(),
                        ]);
                        Notification::query()->create([
                            'tenantId' => $order->tenantId, 'type' => 'AUDIT_ALERT',
                            'title' => 'Payment needs reconciliation', 'message' => $metadata['reviewReason'],
                            'entityId' => $order->id, 'createdAt' => now(),
                        ]);
                    }

                    return 'review';
                }
                if ($order->paymentVerificationRequired && in_array($payment['method'] ?? null, ['CARD', 'TRANSFER'], true)) {
                    $transaction?->forceFill(['method' => $payment['method']])->save();
                }
                $sales->completePending($order, $gatewayRef, verified: true);

                return 'order';
            });
        }

        return null;
    }
}
