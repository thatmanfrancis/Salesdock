<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryAccount;
use App\Models\DeliveryEvent;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DeliveryBooking
{
    public function __construct(private FezDelivery $fez) {}

    /**
     * @param  array<string, mixed>  $recipient
     * @param  array<string, mixed>  $pickup
     */
    public function createForOrder(Order $order, DeliveryAccount $account, array $recipient, array $pickup, float $quotedFee, float $weightKg): Delivery
    {
        return Delivery::query()->updateOrCreate(
            ['orderId' => $order->id],
            [
                'tenantId' => $order->tenantId,
                'deliveryAccountId' => $account->id,
                'branchId' => $order->branchId,
                'provider' => 'FEZ',
                'reference' => 'SD-FEZ-'.strtoupper(substr($order->paymentRef ?: $order->id, -10)),
                'status' => 'NOT_BOOKED',
                'quotedFee' => $quotedFee,
                'weightKg' => max(0.1, $weightKg),
                'pickup' => $pickup,
                'recipient' => $recipient,
            ]
        );
    }

    public function cartWeightKg(string $tenantId, iterable $lines, DeliveryAccount $account): float
    {
        $weight = (float) $account->packagingWeightKg;
        $hasWeight = $weight > 0;

        foreach ($lines as $line) {
            $product = $line['product'] ?? null;
            $qty = (int) ($line['qty'] ?? 1);
            if ($product instanceof Product && $product->weightKg !== null) {
                $weight += (float) $product->weightKg * $qty;
                $hasWeight = true;
            }
        }

        return $hasWeight && $weight > 0 ? round(max(0.1, $weight), 2) : round(max(0.1, (float) $account->defaultWeightKg), 2);
    }

    public function book(Delivery $delivery): Delivery
    {
        return DB::transaction(function () use ($delivery): Delivery {
            $delivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($delivery->providerOrderNo && ! in_array($delivery->status, ['BOOKING_FAILED', 'NOT_BOOKED'], true)) {
                return $this->refresh($delivery);
            }

            try {
                $account = $this->account($delivery);
                $delivery->forceFill(['status' => 'BOOKING', 'lastError' => null])->save();
                $created = $this->fez->createOrder($account, $delivery);
                if (! $created['orderNo']) {
                    throw new RuntimeException('Fez accepted the request but did not return an order number. SalesDock marked it uncertain so it is not booked twice.');
                }

                $delivery->forceFill([
                    'providerOrderNo' => $created['orderNo'],
                    'status' => 'BOOKED',
                    'bookedAt' => now(),
                    'providerPayload' => $created['raw'],
                ])->save();
                $this->event($delivery, 'BOOKED', 'Fez delivery created.', $created['raw']);

                return $this->refresh($delivery);
            } catch (\Throwable $e) {
                $uncertain = $delivery->providerOrderNo || str_contains(strtolower($e->getMessage()), 'did not return an order number');
                $status = $uncertain ? 'BOOKING_UNCERTAIN' : 'BOOKING_FAILED';
                $delivery->forceFill(['status' => $status, 'lastError' => $e->getMessage()])->save();
                $this->event($delivery, $status, $e->getMessage());
                $this->notifyMerchant($delivery, $status === 'BOOKING_UNCERTAIN'
                    ? 'Fez booking needs confirmation'
                    : 'Fez delivery booking failed', $status === 'BOOKING_UNCERTAIN'
                    ? 'SalesDock could not confirm the Fez booking for order '.$delivery->reference.'. Check Fez before retrying so the delivery is not duplicated.'
                    : 'Fez could not book delivery for order '.$delivery->reference.': '.$e->getMessage().' If Fez says the wallet needs funds, top up in the Fez app/portal, then retry here.');
                Log::warning('Fez booking failed', ['delivery' => $delivery->id, 'status' => $status, 'error' => $e->getMessage()]);

                return $delivery->refresh();
            }
        });
    }

    public function refresh(Delivery $delivery): Delivery
    {
        if (! $delivery->providerOrderNo) {
            return $delivery;
        }

        $account = $this->account($delivery);

        try {
            $detail = $this->fez->getOrder($account, $delivery->providerOrderNo);
            $body = (array) ($detail['data'] ?? $detail);
            $providerStatus = (string) ($body['orderStatus'] ?? $body['status'] ?? $delivery->providerStatus ?? '');
            $paymentStatus = (string) ($body['paymentStatus'] ?? $delivery->paymentStatus ?? '');
            $status = $this->fez->normalizeStatus($providerStatus, $paymentStatus);
            $history = $body['history'] ?? $body['trackingHistory'] ?? $delivery->trackingHistory;

            $delivery->forceFill([
                'providerStatus' => $providerStatus ?: $delivery->providerStatus,
                'paymentStatus' => $paymentStatus ?: $delivery->paymentStatus,
                'status' => $status,
                'actualFee' => is_numeric($body['cost'] ?? null) ? (float) $body['cost'] : $delivery->actualFee,
                'providerPayload' => $detail,
                'trackingHistory' => is_array($history) ? $history : $delivery->trackingHistory,
                'proofOfDelivery' => (string) ($body['proofOfDelivery'] ?? $delivery->proofOfDelivery ?? '') ?: $delivery->proofOfDelivery,
                'deliveredAt' => $status === 'DELIVERED' ? ($delivery->deliveredAt ?? now()) : $delivery->deliveredAt,
                'lastError' => $status === 'AWAITING_FEZ_PAYMENT' ? 'Fez is waiting for payment from the merchant Fez wallet. Top up in Fez, then refresh/retry here.' : null,
            ])->save();

            $this->event($delivery, $status, $providerStatus ?: 'Fez status refreshed.', $detail);

            if ($status === 'AWAITING_FEZ_PAYMENT') {
                $this->notifyMerchant($delivery, 'Fez wallet needs funds', 'Fez created delivery '.$delivery->providerOrderNo.' but it is waiting for wallet payment. Top up in the Fez app or Business Portal, then use Refresh on this order.');
            }

            // Also pull the public tracking timeline when available; order detail is authoritative for payment state.
            try {
                $track = $this->fez->track($account, $delivery->providerOrderNo);
                $trackBody = (array) ($track['data'] ?? $track);
                if (! empty($trackBody['history']) && is_array($trackBody['history'])) {
                    $delivery->forceFill(['trackingHistory' => $trackBody['history']])->save();
                }
                if (! empty($trackBody['proofOfDelivery'])) {
                    $delivery->forceFill(['proofOfDelivery' => (string) $trackBody['proofOfDelivery']])->save();
                }
            } catch (\Throwable) {
                // Tracking endpoint variations should not hide the order-detail result.
            }
        } catch (\Throwable $e) {
            $delivery->forceFill(['lastError' => $e->getMessage()])->save();
            Log::warning('Fez delivery refresh failed', ['delivery' => $delivery->id, 'error' => $e->getMessage()]);
        }

        return $delivery->refresh();
    }

    public function cancel(Delivery $delivery, string $reason): Delivery
    {
        if (! $delivery->providerOrderNo) {
            $delivery->forceFill(['status' => 'CANCELLED', 'lastError' => null])->save();
            $this->event($delivery, 'CANCELLED', $reason ?: 'Cancelled before booking.');

            return $delivery;
        }

        if (! in_array($delivery->status, ['NOT_BOOKED', 'BOOKED', 'PENDING_PICKUP', 'AWAITING_FEZ_PAYMENT', 'BOOKING_FAILED'], true)) {
            throw new RuntimeException('This delivery can no longer be cancelled from SalesDock. Check the current Fez status first.');
        }

        $account = $this->account($delivery);
        $result = $this->fez->cancel($account, $delivery->providerOrderNo, $reason);
        $delivery->forceFill(['status' => 'CANCELLED', 'providerStatus' => 'Cancelled', 'lastError' => null])->save();
        $this->event($delivery, 'CANCELLED', $reason, $result);

        return $delivery;
    }

    public function applyWebhook(Delivery $delivery, string $providerStatus, array $payload): Delivery
    {
        $status = $this->fez->normalizeStatus($providerStatus, (string) ($payload['paymentStatus'] ?? $delivery->paymentStatus ?? ''));
        $delivery->forceFill([
            'providerStatus' => $providerStatus,
            'paymentStatus' => (string) ($payload['paymentStatus'] ?? $delivery->paymentStatus ?? '') ?: $delivery->paymentStatus,
            'status' => $status,
            'deliveredAt' => $status === 'DELIVERED' ? ($delivery->deliveredAt ?? now()) : $delivery->deliveredAt,
        ])->save();
        $this->event($delivery, $status, 'Fez webhook: '.$providerStatus, $payload);

        return $delivery;
    }

    /**
     * @return array{name: string, phone: ?string, address: ?string, state: ?string}
     */
    public function pickupSnapshot(DeliveryAccount $account, Tenant $tenant): array
    {
        return [
            'name' => $account->pickupName ?: $tenant->name,
            'phone' => $account->pickupPhone ?: $tenant->phone,
            'address' => $account->pickupAddress ?: $tenant->address,
            'state' => $account->pickupState,
        ];
    }

    private function account(Delivery $delivery): DeliveryAccount
    {
        $account = $delivery->deliveryAccountId
            ? DeliveryAccount::query()->whereKey($delivery->deliveryAccountId)->first()
            : $this->fez->accountFor($delivery->tenantId);

        if (! $account) {
            throw new RuntimeException('This shop has not connected a Fez Business account.');
        }

        return $account;
    }

    private function event(Delivery $delivery, string $status, ?string $description = null, ?array $payload = null): void
    {
        DeliveryEvent::query()->firstOrCreate(
            ['deliveryId' => $delivery->id, 'status' => $status, 'description' => $description],
            ['payload' => $payload, 'occurredAt' => now(), 'createdAt' => now()]
        );
    }

    private function notifyMerchant(Delivery $delivery, string $title, string $message): void
    {
        $exists = Notification::query()
            ->where('tenantId', $delivery->tenantId)
            ->where('entityId', $delivery->orderId)
            ->where('title', $title)
            ->where('createdAt', '>=', now()->subHour())
            ->exists();

        if ($exists) {
            return;
        }

        Notification::query()->create([
            'tenantId' => $delivery->tenantId,
            'type' => 'DELIVERY_ATTENTION',
            'title' => $title,
            'message' => $message,
            'entityId' => $delivery->orderId,
            'createdAt' => now(),
        ]);
    }
}
