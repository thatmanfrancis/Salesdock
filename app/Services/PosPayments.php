<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StorefrontConfig;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosPayments
{
    public function __construct(private PaymentManager $payments, private BillingService $billing, private SaleRecorder $sales) {}

    public function assertReady(string $tenantId): void
    {
        $gateway = $this->payments->active();
        $store = StorefrontConfig::query()->where('tenantId', $tenantId)->first();
        if (! $gateway->ready() || ! $store?->{$gateway->subaccountColumn()}) {
            throw ValidationException::withMessages(['payment' => 'Connect a collection bank account in Settings → Payments before using verified checkout.']);
        }
        if (! filter_var(Tenant::query()->whereKey($tenantId)->value('email'), FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['payment' => 'Add a valid business email in Settings before using verified checkout.']);
        }
    }

    public function start(Order $order): string
    {
        $transaction = Transaction::query()->where('orderId', $order->id)->firstOrFail();
        $gateway = $this->payments->driver(strtolower($transaction->gateway));
        $store = StorefrontConfig::query()->where('tenantId', $order->tenantId)->first();
        if (! $gateway->ready() || ! $store?->{$gateway->subaccountColumn()}) {
            throw ValidationException::withMessages(['payment' => 'The collection account for this payment provider is not connected.']);
        }

        $attempt = DB::transaction(function () use ($order, $gateway): array {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! $order->paymentVerificationRequired || $order->status !== 'PENDING') {
                throw ValidationException::withMessages(['payment' => 'This sale is not awaiting verified payment.']);
            }
            $transaction = Transaction::query()->where('orderId', $order->id)->lockForUpdate()->firstOrFail();
            $metadata = $transaction->metadata ?? [];
            if (! empty($metadata['checkoutUrl'])) {
                return $metadata;
            }
            if (! empty($metadata['providerReference'])) {
                throw ValidationException::withMessages(['payment' => 'The previous payment request is awaiting confirmation. Check payment status before retrying.']);
            }
            $metadata['providerReference'] = $gateway->attemptReference($order->paymentRef);
            $metadata['checkoutState'] = 'STARTING';
            $transaction->forceFill(['metadata' => $metadata, 'status' => 'PENDING'])->save();

            return $metadata;
        });
        if (! empty($attempt['checkoutUrl'])) {
            return $attempt['checkoutUrl'];
        }

        try {
            $tenant = Tenant::query()->findOrFail($order->tenantId);
            $url = $gateway->checkoutLink([
                'reference' => $order->paymentRef,
                'attemptReference' => $attempt['providerReference'],
                'amount' => (float) $order->netAmount,
                'email' => $order->customerEmail ?: $tenant->email,
                'name' => $order->customerName ?: $tenant->name,
                'redirect' => route('pos.payments.return', $order),
                'title' => 'SalesDock',
                'description' => 'In-store sale '.$order->paymentRef,
                'subaccountId' => $store->{$gateway->subaccountColumn()},
            ]);
        } catch (\Throwable $exception) {
            $this->updateAttempt($transaction, ['checkoutState' => 'UNCERTAIN', 'lastError' => $exception->getMessage()]);
            throw $exception;
        }
        $this->updateAttempt($transaction, ['checkoutState' => 'READY', 'checkoutUrl' => $url, 'lastError' => null]);

        return $url;
    }

    public function reconcile(Order $order): void
    {
        $transaction = Transaction::query()->where('orderId', $order->id)->firstOrFail();
        $metadata = $transaction->metadata ?? [];
        if (! $order->paymentVerificationRequired || empty($metadata['providerReference'])) {
            return;
        }
        $gateway = $this->payments->driver(strtolower($transaction->gateway));
        $payment = $gateway->verifyByReference($metadata['providerReference']);
        $this->billing->settlePayment($payment, $gateway, $this->sales);
        if (in_array(strtolower((string) ($payment['status'] ?? '')), ['failed', 'cancelled', 'canceled'], true)
            && ($payment['tx_ref'] ?? null) === $metadata['providerReference']) {
            DB::transaction(function () use ($order, $metadata): void {
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);
                $transaction = Transaction::query()->where('orderId', $order->id)->lockForUpdate()->firstOrFail();
                $current = $transaction->metadata ?? [];
                if ($order->status !== 'PENDING' || ($current['providerReference'] ?? null) !== $metadata['providerReference']) {
                    return;
                }
                $current['previousReferences'][] = $current['providerReference'];
                unset($current['providerReference'], $current['checkoutUrl']);
                $current['checkoutState'] = 'FAILED';
                $transaction->forceFill(['status' => 'FAILED', 'metadata' => $current])->save();
            });
        }
        $this->updateAttempt($transaction, ['lastCheckedAt' => now()->toIso8601String(), 'lastError' => null]);
    }

    private function updateAttempt(Transaction $transaction, array $changes): void
    {
        DB::transaction(function () use ($transaction, $changes): void {
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $transaction->forceFill(['metadata' => array_replace($transaction->metadata ?? [], $changes)])->save();
        });
    }
}
