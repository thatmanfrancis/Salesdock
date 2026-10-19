<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\PlatformSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Paystack hosted checkout + subaccounts for storefront split settlements.
 */
class Paystack implements PaymentGateway
{
    private const BASE_URL = 'https://api.paystack.co';

    private const ATTEMPT_SEPARATOR = '__';

    public function name(): string
    {
        return 'paystack';
    }

    public function label(): string
    {
        return 'Paystack';
    }

    public function subaccountColumn(): string
    {
        return 'paystackSubaccountCode';
    }

    public function ready(): bool
    {
        return $this->secretKey() !== null;
    }

    public function publicKey(): ?string
    {
        $key = trim(PlatformSetting::payment()['paystack']['public_key']);

        return preg_match('/^pk_(?:test|live)_[a-zA-Z0-9]+$/', $key) ? $key : null;
    }

    public function secretKey(): ?string
    {
        $key = trim(PlatformSetting::payment()['paystack']['secret_key']);

        return preg_match('/^sk_(?:test|live)_[a-zA-Z0-9]+$/', $key) ? $key : null;
    }

    public function attemptReference(string $reference): string
    {
        return $reference.self::ATTEMPT_SEPARATOR.strtoupper(Str::random(6));
    }

    public function baseReference(string $txRef): string
    {
        return explode(self::ATTEMPT_SEPARATOR, $txRef, 2)[0];
    }

    /**
     * @return array<string, string>
     */
    public function banks(): array
    {
        if ($this->ready()) {
            try {
                $banks = Cache::remember('paystack.banks.ng', now()->addDay(), function () {
                    $rows = $this->request('get', '/bank', ['country' => 'nigeria', 'perPage' => 100])['data'] ?? [];
                    $banks = [];
                    foreach ($rows as $row) {
                        $code = trim((string) ($row['code'] ?? ''));
                        $name = trim((string) ($row['name'] ?? ''));
                        if ($code !== '' && $name !== '') {
                            $banks[$code] = $name;
                        }
                    }
                    asort($banks, SORT_NATURAL | SORT_FLAG_CASE);

                    return $banks;
                });
                if ($banks !== []) {
                    return $banks;
                }
            } catch (Throwable) {
                // Fall through.
            }
        }

        return $this->fallbackBanks();
    }

    public function resolveAccount(string $bankCode, string $accountNumber): string
    {
        $data = $this->request('get', '/bank/resolve', [
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
        ])['data'] ?? [];

        $name = trim((string) ($data['account_name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Could not confirm that account number with the bank.');
        }

        return $name;
    }

    public function createSubaccount(array $shop): string
    {
        $body = $this->request('post', '/subaccount', [
            'business_name' => $shop['businessName'],
            'bank_code' => $shop['bankCode'],
            'account_number' => $shop['accountNumber'],
            'percentage_charge' => $this->platformSplitPercent(),
            'primary_contact_email' => $shop['email'],
            'primary_contact_name' => $shop['contactName'] ?? $shop['businessName'],
            'primary_contact_phone' => $shop['mobile'],
        ]);

        $code = (string) ($body['data']['subaccount_code'] ?? '');
        if ($code === '') {
            throw new RuntimeException('Paystack did not return a subaccount code.');
        }

        return $code;
    }

    public function updateSubaccount(string $subaccountId, array $shop): void
    {
        $this->request('put', '/subaccount/'.$subaccountId, [
            'business_name' => $shop['businessName'],
            'bank_code' => $shop['bankCode'],
            'account_number' => $shop['accountNumber'],
            'percentage_charge' => $this->platformSplitPercent(),
            'primary_contact_email' => $shop['email'],
            'primary_contact_name' => $shop['contactName'] ?? $shop['businessName'],
            'primary_contact_phone' => $shop['mobile'],
        ]);
    }

    public function checkoutLink(array $payment): string
    {
        $payload = [
            'email' => $payment['email'],
            'amount' => (int) round($payment['amount'] * 100),
            'currency' => 'NGN',
            'reference' => $this->attemptReference($payment['reference']),
            'callback_url' => $payment['redirect'],
            'metadata' => [
                'reference' => $payment['reference'],
                'title' => $payment['title'],
                'description' => $payment['description'],
                'custom_fields' => array_values(array_filter([
                    isset($payment['name']) ? ['display_name' => 'Name', 'variable_name' => 'name', 'value' => $payment['name']] : null,
                    isset($payment['phone']) ? ['display_name' => 'Phone', 'variable_name' => 'phone', 'value' => $payment['phone']] : null,
                ])),
            ],
        ];

        $subaccountId = trim((string) ($payment['subaccountId'] ?? ''));
        if ($subaccountId !== '') {
            $payload['subaccount'] = $subaccountId;
        }

        $body = $this->request('post', '/transaction/initialize', $payload);
        $link = $body['data']['authorization_url'] ?? null;
        if (! is_string($link) || ! str_starts_with($link, 'https://')) {
            throw new RuntimeException('Paystack did not return a payment link.');
        }

        return $link;
    }

    public function verifyTransaction(string $transactionId): array
    {
        // Paystack verify is reference-based; numeric ids still work via /transaction/{id}.
        if (ctype_digit($transactionId)) {
            return $this->normalize($this->request('get', '/transaction/'.$transactionId)['data'] ?? []);
        }

        return $this->verifyByReference($transactionId);
    }

    public function verifyByReference(string $txRef): array
    {
        return $this->normalize($this->request('get', '/transaction/verify/'.rawurlencode($txRef))['data'] ?? []);
    }

    public function successful(?string $status): bool
    {
        return in_array(strtolower((string) $status), ['success', 'successful'], true);
    }

    public function confirms(array $payment, string $reference, float $expectedAmount): bool
    {
        return $this->successful($payment['status'] ?? null)
            && $this->baseReference((string) ($payment['tx_ref'] ?? '')) === $reference
            && strtoupper((string) ($payment['currency'] ?? '')) === 'NGN'
            && (float) ($payment['amount'] ?? 0) + 0.009 >= $expectedAmount;
    }

    public function webhookTrusted(Request $request): bool
    {
        $secret = $this->secretKey();
        $signature = (string) $request->header('x-paystack-signature', '');
        if (! $secret || $signature === '') {
            return false;
        }

        $computed = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($computed, $signature);
    }

    private function platformSplitPercent(): float
    {
        return round(max(0.0, min(0.5, (float) PlatformSetting::payment()['platform_split'])) * 100, 4);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: string, tx_ref: string, amount: float, currency: string, status: string, created_at: ?string, gateway: string}
     */
    private function normalize(array $data): array
    {
        $amountKobo = (float) ($data['amount'] ?? 0);

        return [
            'id' => (string) ($data['id'] ?? ''),
            'tx_ref' => (string) ($data['reference'] ?? ''),
            'amount' => round($amountKobo / 100, 2),
            'currency' => strtoupper((string) ($data['currency'] ?? 'NGN')),
            'status' => (string) ($data['status'] ?? ''),
            'created_at' => isset($data['paid_at']) ? (string) $data['paid_at'] : (isset($data['created_at']) ? (string) $data['created_at'] : null),
            'gateway' => 'PAYSTACK',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fallbackBanks(): array
    {
        return [
            '044' => 'Access Bank',
            '023' => 'Citibank Nigeria',
            '050' => 'Ecobank Nigeria',
            '070' => 'Fidelity Bank',
            '011' => 'First Bank of Nigeria',
            '214' => 'First City Monument Bank',
            '058' => 'Guaranty Trust Bank',
            '030' => 'Heritage Bank',
            '301' => 'Keystone Bank',
            '076' => 'Polaris Bank',
            '101' => 'Providus Bank',
            '221' => 'Stanbic IBTC Bank',
            '068' => 'Standard Chartered Bank',
            '232' => 'Sterling Bank',
            '100' => 'Suntrust Bank',
            '032' => 'Union Bank of Nigeria',
            '033' => 'United Bank for Africa',
            '215' => 'Unity Bank',
            '035' => 'Wema Bank',
            '057' => 'Zenith Bank',
        ];
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $secret = $this->secretKey();
        if (! $secret) {
            throw new RuntimeException('Paystack is not configured. Add keys under Admin → Settings → Payments.');
        }

        try {
            $pending = Http::withToken($secret)->acceptJson()->timeout(20);
            $response = $method === 'get'
                ? $pending->get(self::BASE_URL.$path, $data)
                : $pending->{$method}(self::BASE_URL.$path, $data);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach Paystack. Please try again in a moment.');
        }

        $body = $response->json() ?? [];
        if (! $response->successful() || ($body['status'] ?? false) !== true) {
            throw new RuntimeException((string) ($body['message'] ?? 'Paystack request failed ('.$response->status().').'));
        }

        return $body;
    }
}
