<?php

namespace App\Http\Controllers\Merchant;

use App\Models\Delivery;
use App\Models\DeliveryAccount;
use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\DeliveryBooking;
use App\Services\FezDelivery;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeliveryController extends MerchantController
{
    public function updateAccount(Request $request, FezDelivery $fez): RedirectResponse
    {
        abort_unless($this->canManageDeliverySettings(), 403);

        $data = $request->validate([
            'environment' => ['required', 'in:sandbox,production'],
            'baseUrl' => ['nullable', 'url', 'max:255'],
            'fezUserId' => ['nullable', 'string', 'max:255'],
            'fezPassword' => ['nullable', 'string', 'max:255'],
            'fezSecretKey' => ['nullable', 'string', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
            'autoBook' => ['nullable', 'boolean'],
            'pickupName' => ['nullable', 'string', 'max:255'],
            'pickupPhone' => ['nullable', 'string', 'max:50'],
            'pickupAddress' => ['nullable', 'string', 'max:1000'],
            'pickupState' => ['nullable', 'string', 'in:'.implode(',', FezDelivery::states())],
            'defaultWeightKg' => ['nullable', 'numeric', 'min:0.1', 'max:1000'],
            'packagingWeightKg' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $account = DeliveryAccount::query()->firstOrNew(['tenantId' => $this->tenantId()]);
        $isNew = ! $account->exists;
        $userId = trim((string) ($data['fezUserId'] ?? '')) ?: $account->userId;
        $password = trim((string) ($data['fezPassword'] ?? '')) ?: $account->password;
        $secret = trim((string) ($data['fezSecretKey'] ?? '')) ?: $account->secretKey;

        if (($isNew || $request->boolean('enabled')) && (! $userId || ! $password)) {
            return back()->withErrors(['fezUserId' => 'Enter the Fez Business user ID and password from your own Fez account before enabling delivery.'])->withInput();
        }

        $credentialsChanged = $isNew
            || $userId !== $account->userId
            || $password !== $account->password
            || ($data['environment'] !== $account->environment)
            || (($data['baseUrl'] ?? null) !== $account->baseUrl);

        $account->forceFill([
            'provider' => 'FEZ',
            'environment' => $data['environment'],
            'baseUrl' => $data['baseUrl'] ?? null,
            'userId' => $userId,
            'password' => $password,
            'secretKey' => $secret,
            'enabled' => $request->boolean('enabled'),
            'autoBook' => $request->boolean('autoBook'),
            'pickupName' => $data['pickupName'] ?? null,
            'pickupPhone' => $data['pickupPhone'] ?? null,
            'pickupAddress' => $data['pickupAddress'] ?? null,
            'pickupState' => $data['pickupState'] ?? null,
            'defaultWeightKg' => $data['defaultWeightKg'] ?? 1,
            'packagingWeightKg' => $data['packagingWeightKg'] ?? 0,
            'status' => $credentialsChanged ? 'NOT_CONNECTED' : $account->status,
            'authToken' => $credentialsChanged ? null : $account->authToken,
            'tokenExpiresAt' => $credentialsChanged ? null : $account->tokenExpiresAt,
            'lastError' => $credentialsChanged ? null : $account->lastError,
        ])->save();

        return redirect()->route('settings', ['section' => 'delivery'])->with('status', 'Fez delivery settings saved. Use Test connection to confirm your own Fez Business account works. Funding stays in Fez — SalesDock does not collect delivery top-ups.');
    }

    public function testAccount(FezDelivery $fez): RedirectResponse
    {
        abort_unless($this->canManageDeliverySettings(), 403);
        $account = DeliveryAccount::query()->where('tenantId', $this->tenantId())->first();

        if (! $account) {
            return back()->withErrors(['fezUserId' => 'Save your Fez Business credentials first.']);
        }

        try {
            $fez->authenticate($account, true);
        } catch (\Throwable $e) {
            return back()->withErrors(['fezUserId' => $e->getMessage()]);
        }

        return back()->with('status', 'Fez connection successful'.($account->orgName ? ' for '.$account->orgName : '').'.');
    }

    public function book(Order $order, DeliveryBooking $booking): RedirectResponse
    {
        $delivery = $this->deliveryForOrder($order);
        if ($order->status !== 'COMPLETED') {
            return back()->withErrors(['delivery' => 'Book Fez only after this order has been paid/completed.']);
        }

        $booking->book($delivery);

        return back()->with('status', $delivery->refresh()->status === 'AWAITING_FEZ_PAYMENT'
            ? 'Fez created the delivery but is waiting for wallet payment. Top up in Fez, then refresh this order.'
            : 'Fez delivery updated.');
    }

    public function refresh(Delivery $delivery, DeliveryBooking $booking): RedirectResponse
    {
        $this->guardDelivery($delivery);
        $booking->refresh($delivery);

        return back()->with('status', 'Fez delivery status refreshed.');
    }

    public function cancel(Request $request, Delivery $delivery, DeliveryBooking $booking): RedirectResponse
    {
        $this->guardDelivery($delivery);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $booking->cancel($delivery, (string) ($data['reason'] ?? 'Cancelled from SalesDock'));
        } catch (\Throwable $e) {
            return back()->withErrors(['delivery' => $e->getMessage()]);
        }

        return back()->with('status', 'Fez delivery cancelled. Any eligible refund goes back to the merchant Fez wallet.');
    }

    public function webhook(Request $request, FezDelivery $fez, DeliveryBooking $booking): JsonResponse
    {
        $payload = (array) $request->json()->all();
        $orderNumber = (string) ($payload['orderNumber'] ?? $payload['orderNo'] ?? '');
        $status = (string) ($payload['status'] ?? $payload['orderStatus'] ?? '');

        $event = WebhookEvent::query()->firstOrCreate(
            ['reference' => 'FEZ:'.$orderNumber.':'.($status ?: 'delivery_update').':'.(string) $request->header('X-Timestamp', '')],
            [
                'gateway' => 'FEZ',
                'eventType' => $status ?: 'delivery_update',
                'payload' => $payload,
                'createdAt' => now(),
            ]
        );
        if ($event->processed) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        if ($orderNumber === '' || $status === '') {
            return response()->json(['ok' => false, 'message' => 'Missing orderNumber or status.'], 422);
        }

        $delivery = Delivery::query()->where('providerOrderNo', $orderNumber)->first();
        if (! $delivery) {
            Log::warning('Fez webhook for unknown delivery', ['orderNumber' => $orderNumber]);

            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $account = $delivery->deliveryAccountId
            ? DeliveryAccount::query()->whereKey($delivery->deliveryAccountId)->first()
            : DeliveryAccount::query()->where('tenantId', $delivery->tenantId)->first();

        if (! $account || ! $fez->webhookSignatureValid(
            $account,
            $orderNumber,
            $status,
            (string) $request->header('X-Timestamp', ''),
            (string) $request->header('X-Signature', '')
        )) {
            return response()->json(['ok' => false, 'message' => 'Invalid Fez webhook signature.'], 401);
        }

        $booking->applyWebhook($delivery, $status, $payload);
        $event->forceFill(['processed' => true, 'processedAt' => now()])->save();

        return response()->json(['ok' => true]);
    }

    private function deliveryForOrder(Order $order): Delivery
    {
        abort_unless($order->tenantId === $this->tenantId(), 404);
        $delivery = Delivery::query()->where('orderId', $order->id)->where('tenantId', $this->tenantId())->first();
        abort_unless($delivery, 404);

        return $delivery;
    }

    private function guardDelivery(Delivery $delivery): void
    {
        abort_unless($delivery->tenantId === $this->tenantId(), 404);
    }

    private function canManageDeliverySettings(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }
        if ($user->role?->name === 'Staff Admin') {
            return false;
        }

        $role = $user->role?->role ?? 'CASHIER';
        if (! in_array($role, ['MANAGER', 'ADMIN', 'SUPER_ADMIN'], true)) {
            return false;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageSettings']);
    }
}
