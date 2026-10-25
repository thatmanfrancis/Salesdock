<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryAccount;
use App\Models\OrderItem;
use App\Models\PlatformSetting;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FezDelivery
{
    /**
     * Nigerian states + FCT accepted as Fez destination/pickup states.
     *
     * @return list<string>
     */
    public static function states(): array
    {
        return [
            'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
            'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT - Abuja', 'Gombe',
            'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos',
            'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto',
            'Taraba', 'Yobe', 'Zamfara',
        ];
    }

    public function enabled(): bool
    {
        return (bool) PlatformSetting::delivery()['enabled'];
    }

    public function accountFor(string $tenantId): ?DeliveryAccount
    {
        if (! $this->enabled()) {
            return null;
        }

        $account = DeliveryAccount::query()->where('tenantId', $tenantId)->first();

        return $account?->connected() ? $account : null;
    }

    public function baseUrl(DeliveryAccount $account): string
    {
        if (filled($account->baseUrl)) {
            return rtrim((string) $account->baseUrl, '/');
        }

        $config = PlatformSetting::delivery();
        $url = $account->environment === 'production'
            ? (string) $config['production_base_url']
            : (string) $config['sandbox_base_url'];

        if ($url === '') {
            throw new RuntimeException('Fez '.($account->environment === 'production' ? 'production' : 'sandbox').' API URL is not configured. Ask SalesDock admin to add it under Platform Admin → Settings → Delivery, or ask Fez for the production base URL.');
        }

        return rtrim($url, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function authenticate(DeliveryAccount $account, bool $force = false): array
    {
        if (! $force && filled($account->authToken) && $account->tokenExpiresAt && $account->tokenExpiresAt->isAfter(now()->addMinute())) {
            return ['authToken' => $account->authToken];
        }

        $response = Http::timeout(25)->acceptJson()->asJson()->post($this->baseUrl($account).'/user/authenticate', [
            'user_id' => $account->userId,
            'password' => $account->password,
        ]);

        if (! $response->successful()) {
            $message = $this->message($response->json(), 'Fez authentication failed. Check the Fez user ID and password.');
            $account->forceFill(['status' => 'ERROR', 'lastError' => $message, 'lastTestedAt' => now()])->save();
            throw new RuntimeException($message);
        }

        $data = (array) $response->json();
        $auth = (array) ($data['authDetails'] ?? []);
        $org = (array) ($data['orgDetails'] ?? $data['organizationDetails'] ?? []);
        $user = (array) ($data['userDetails'] ?? []);
        $token = (string) ($auth['authToken'] ?? $auth['token'] ?? '');

        if ($token === '') {
            throw new RuntimeException('Fez authenticated but did not return an access token.');
        }

        $secret = (string) ($org['secret-key'] ?? $org['secretKey'] ?? $org['secret_key'] ?? $account->secretKey ?? '');
        $account->forceFill([
            'authToken' => $token,
            'tokenExpiresAt' => $this->expiry($auth['expireToken'] ?? $auth['expiresAt'] ?? null),
            'secretKey' => $secret !== '' ? $secret : $account->secretKey,
            'orgId' => (string) ($org['id'] ?? $org['org_id'] ?? $org['orgId'] ?? $account->orgId ?? '') ?: $account->orgId,
            'orgName' => (string) ($org['orgName'] ?? $org['name'] ?? $org['Org Full Name'] ?? $account->orgName ?? '') ?: $account->orgName,
            'status' => 'CONNECTED',
            'lastTestedAt' => now(),
            'lastError' => null,
        ])->save();

        Log::info('Fez account authenticated', ['tenantId' => $account->tenantId, 'environment' => $account->environment, 'org' => $account->orgName]);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function quote(DeliveryAccount $account, string $destinationState, ?string $pickupState, float $weightKg): array
    {
        $data = $this->request($account, 'POST', '/order/cost', [
            'state' => $destinationState,
            'pickUpState' => $pickupState ?: $account->pickupState,
            'weight' => max(0.1, $weightKg),
        ]);
        $body = (array) ($data['data'] ?? $data);
        $cost = $this->money($body['cost'] ?? null);
        $vat = $this->money($body['vat'] ?? $body['VAT'] ?? null);
        $total = $this->money($body['totalCost'] ?? $body['total_cost'] ?? $body['total'] ?? null);
        $total ??= ($cost !== null || $vat !== null) ? round(($cost ?? 0) + ($vat ?? 0), 2) : null;

        if ($total === null) {
            throw new RuntimeException('Fez returned a delivery quote without a total cost.');
        }

        return ['cost' => $cost, 'vat' => $vat, 'totalCost' => $total, 'raw' => $data];
    }

    /**
     * @return array{orderNo: ?string, raw: array<string, mixed>}
     */
    public function createOrder(DeliveryAccount $account, Delivery $delivery): array
    {
        $recipient = (array) $delivery->recipient;
        $pickup = (array) $delivery->pickup;
        $order = $delivery->order;
        $items = OrderItem::query()->where('orderId', $delivery->orderId)->get();
        $names = Product::query()->whereIn('id', $items->pluck('productId'))->pluck('name', 'id');
        $description = $items->map(fn (OrderItem $item): string => $item->quantity.' × '.($names[$item->productId] ?? 'item'))->implode(', ');
        if ($description === '') {
            $description = 'SalesDock order '.$delivery->reference;
        }

        $payload = [[
            'recipientAddress' => (string) ($recipient['address'] ?? ''),
            'recipientState' => (string) ($recipient['state'] ?? ''),
            'recipientName' => (string) ($recipient['name'] ?? ''),
            'recipientPhone' => (string) ($recipient['phone'] ?? ''),
            'recipientEmail' => (string) ($recipient['email'] ?? ''),
            'recipientAlternatePhone' => (string) ($recipient['alternatePhone'] ?? ''),
            'uniqueID' => $delivery->reference,
            'BatchID' => $delivery->reference,
            'valueOfItem' => (float) ($order?->totalAmount ?? $delivery->quotedFee),
            'weight' => max(0.1, (float) $delivery->weightKg),
            'pickUpState' => (string) ($pickup['state'] ?? $account->pickupState ?? ''),
            'pickUpAddress' => (string) ($pickup['address'] ?? $account->pickupAddress ?? ''),
            'itemDescription' => $description,
            'additionalDetails' => (string) ($recipient['notes'] ?? ''),
            'fragile' => false,
        ]];

        $senderName = (string) ($pickup['name'] ?? $account->pickupName ?? '');
        $senderAddress = (string) ($pickup['address'] ?? $account->pickupAddress ?? '');
        $senderPhone = (string) ($pickup['phone'] ?? $account->pickupPhone ?? '');
        if ($senderName !== '' && $senderAddress !== '' && $senderPhone !== '') {
            $payload[0] += [
                'thirdparty' => 'true',
                'senderName' => $senderName,
                'senderAddress' => $senderAddress,
                'senderPhone' => $senderPhone,
            ];
        }

        $data = $this->request($account, 'POST', '/order', $payload);
        $orderNos = (array) ($data['orderNos'] ?? $data['data']['orderNos'] ?? []);
        $orderNo = $orderNos[$delivery->reference] ?? $orderNos[0] ?? null;

        return ['orderNo' => $orderNo ? (string) $orderNo : null, 'raw' => $data];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(DeliveryAccount $account, string $providerOrderNo): array
    {
        return $this->request($account, 'GET', '/orders/'.rawurlencode($providerOrderNo));
    }

    /**
     * @return array<string, mixed>
     */
    public function track(DeliveryAccount $account, string $providerOrderNo): array
    {
        return $this->request($account, 'GET', '/order/track/'.rawurlencode($providerOrderNo));
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(DeliveryAccount $account, string $providerOrderNo, string $reason): array
    {
        return $this->request($account, 'POST', '/order/cancel', [
            'orderNo' => $providerOrderNo,
            'reason' => $reason,
        ]);
    }

    public function normalizeStatus(?string $providerStatus, ?string $paymentStatus = null): string
    {
        $status = strtolower(trim((string) $providerStatus));
        $payment = strtolower(trim((string) $paymentStatus));

        if ($payment === 'unpaid' || $status === 'pending payment' || str_contains($status, 'pending payment')) {
            return 'AWAITING_FEZ_PAYMENT';
        }

        return match (true) {
            $status === '' => 'BOOKED',
            str_contains($status, 'delivered') => 'DELIVERED',
            str_contains($status, 'cancel') => 'CANCELLED',
            str_contains($status, 'return') => 'RETURNED',
            str_contains($status, 'picked') => 'PICKED_UP',
            str_contains($status, 'dispatch') || str_contains($status, 'transit') || str_contains($status, 'out for delivery') => 'IN_TRANSIT',
            str_contains($status, 'drop') || str_contains($status, 'pick-up') || str_contains($status, 'pickup') || str_contains($status, 'pending') => 'PENDING_PICKUP',
            default => strtoupper(str_replace([' ', '-'], '_', trim((string) $providerStatus))),
        };
    }

    public function webhookSignatureValid(DeliveryAccount $account, string $orderNumber, string $status, string $timestamp, string $signature): bool
    {
        $secret = trim((string) $account->secretKey);
        if ($secret === '' || $signature === '') {
            return false;
        }

        $message = $orderNumber.$status.$timestamp;
        $candidates = [
            hash_hmac('sha256', $message, $secret),
            hash('sha256', $secret.$message),
            hash('sha256', $message.$secret),
            hash('sha256', $message),
        ];

        foreach ($candidates as $candidate) {
            if (hash_equals(strtolower($candidate), strtolower($signature))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(DeliveryAccount $account, string $method, string $path, array $payload = [], bool $retry = true): array
    {
        $this->authenticate($account);
        $account->refresh();

        $request = Http::timeout(30)->acceptJson()->withHeaders([
            'Authorization' => 'Bearer '.$account->authToken,
            'secret-key' => (string) $account->secretKey,
        ]);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($this->baseUrl($account).$path),
            default => $request->asJson()->post($this->baseUrl($account).$path, $payload),
        };

        if ($response->status() === 401 && $retry) {
            $this->authenticate($account, true);

            return $this->request($account, $method, $path, $payload, false);
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->message($response->json(), 'Fez request failed ('.$response->status().').'));
        }

        return (array) $response->json();
    }

    private function expiry(mixed $value): Carbon
    {
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                // Fall through to a conservative one-hour token cache.
            }
        }

        return now()->addHour();
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function message(?array $body, string $fallback): string
    {
        if (! $body) {
            return $fallback;
        }

        foreach (['message', 'error', 'description'] as $key) {
            if (! empty($body[$key]) && is_scalar($body[$key])) {
                return (string) $body[$key];
            }
        }

        if (! empty($body['errors']) && is_array($body['errors'])) {
            $first = collect($body['errors'])->flatten()->first();

            return $first ? (string) $first : $fallback;
        }

        return $fallback;
    }

    private function money(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
