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
 * Flutterwave v3 Standard (hosted checkout): the server creates a payment link, the customer pays on
 * Flutterwave's page, and every result is verified server-side before anything is marked paid.
 */
class Flutterwave implements PaymentGateway
{
    private const BASE_URL = 'https://api.flutterwave.com/v3';

    /** Separates our invoice/order reference from the per-attempt suffix in tx_ref. */
    private const ATTEMPT_SEPARATOR = '__';

    public function name(): string
    {
        return 'flutterwave';
    }

    public function label(): string
    {
        return 'Flutterwave';
    }

    public function subaccountColumn(): string
    {
        return 'flutterwaveSubaccountId';
    }

    public function ready(): bool
    {
        return $this->secretKey() !== null;
    }

    public function publicKey(): ?string
    {
        $key = trim(PlatformSetting::payment()['flutterwave']['public_key']);

        return preg_match('/^FLWPUBK(?:_(?:TEST|LIVE))?-.+-X$/', $key) ? $key : null;
    }

    public function secretKey(): ?string
    {
        $key = trim(PlatformSetting::payment()['flutterwave']['secret_key']);

        return preg_match('/^FLWSECK(?:_(?:TEST|LIVE))?-.+-X$/', $key) ? $key : null;
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
                $banks = Cache::remember('flutterwave.banks.ng', now()->addDay(), function () {
                    $rows = $this->request('get', '/banks/NG')['data'] ?? [];
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
                // Fall through to the short local list so onboarding still works offline.
            }
        }

        return $this->fallbackBanks();
    }

    public function resolveAccount(string $bankCode, string $accountNumber): string
    {
        $data = $this->request('post', '/accounts/resolve', [
            'account_number' => $accountNumber,
            'account_bank' => $bankCode,
        ])['data'] ?? [];

        $name = trim((string) ($data['account_name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Could not confirm that account number with the bank.');
        }

        return $name;
    }

    public function createSubaccount(array $shop): string
    {
        $body = $this->request('post', '/subaccounts', [
            'account_bank' => $shop['bankCode'],
            'account_number' => $shop['accountNumber'],
            'business_name' => $shop['businessName'],
            'business_email' => $shop['email'],
            'business_contact' => $shop['contactName'] ?? $shop['businessName'],
            'business_contact_mobile' => $shop['mobile'],
            'business_mobile' => $shop['mobile'],
            'country' => 'NG',
            'split_type' => 'percentage',
            'split_value' => $this->platformSplit(),
        ]);

        $id = (string) ($body['data']['subaccount_id'] ?? $body['data']['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Flutterwave did not return a subaccount id.');
        }

        return $id;
    }

    public function updateSubaccount(string $subaccountId, array $shop): void
    {
        $this->request('put', '/subaccounts/'.$subaccountId, [
            'account_bank' => $shop['bankCode'],
            'account_number' => $shop['accountNumber'],
            'business_name' => $shop['businessName'],
            'business_email' => $shop['email'],
            'business_mobile' => $shop['mobile'],
            'split_type' => 'percentage',
            'split_value' => $this->platformSplit(),
        ]);
    }

    public function checkoutLink(array $payment): string
    {
        $payload = [
            'tx_ref' => $payment['attemptReference'] ?? $this->attemptReference($payment['reference']),
            'amount' => round($payment['amount'], 2),
            'currency' => 'NGN',
            'redirect_url' => $payment['redirect'],
            'payment_options' => 'card,banktransfer,ussd',
            'customer' => array_filter([
                'email' => $payment['email'],
                'name' => $payment['name'] ?? null,
                'phonenumber' => $payment['phone'] ?? null,
            ]),
            'customizations' => [
                'title' => $payment['title'],
                'description' => $payment['description'],
            ],
            'meta' => ['reference' => $payment['reference']],
        ];

        $subaccountId = trim((string) ($payment['subaccountId'] ?? ''));
        if ($subaccountId !== '') {
            $payload['subaccounts'] = [['id' => $subaccountId]];
        }

        $body = $this->request('post', '/payments', $payload);

        $link = $body['data']['link'] ?? null;
        if (! is_string($link) || ! str_starts_with($link, 'https://')) {
            throw new RuntimeException('Flutterwave did not return a payment link.');
        }

        return $link;
    }

    public function verifyTransaction(string $transactionId): array
    {
        if (! ctype_digit($transactionId)) {
            throw new RuntimeException('Invalid Flutterwave transaction id.');
        }

        return $this->normalize($this->request('get', '/transactions/'.$transactionId.'/verify')['data'] ?? []);
    }

    public function verifyByReference(string $txRef): array
    {
        return $this->normalize($this->request('get', '/transactions/verify_by_reference', ['tx_ref' => $txRef])['data'] ?? []);
    }

    public function successful(?string $status): bool
    {
        return in_array(strtolower((string) $status), ['successful', 'success', 'completed'], true);
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
        $secret = trim(PlatformSetting::payment()['flutterwave']['secret_hash']);
        $header = $request->header('verif-hash');
        if ($secret === '' || $header === null || strlen($header) !== strlen($secret)) {
            return false;
        }

        return hash_equals($secret, $header);
    }

    private function platformSplit(): float
    {
        return max(0.0, min(0.5, (float) PlatformSetting::payment()['platform_split']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: string, tx_ref: string, amount: float, currency: string, status: string, created_at: ?string, gateway: string}
     */
    private function normalize(array $data): array
    {
        return [
            'id' => (string) ($data['id'] ?? ''),
            'tx_ref' => (string) ($data['tx_ref'] ?? ''),
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? 'NGN')),
            'status' => (string) ($data['status'] ?? ''),
            'created_at' => isset($data['created_at']) ? (string) $data['created_at'] : null,
            'gateway' => 'FLUTTERWAVE',
            'method' => match ($data['payment_type'] ?? null) {
                'card' => 'CARD',
                'banktransfer', 'bank_transfer', 'account' => 'TRANSFER',
                default => null,
            },
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
            throw new RuntimeException('Flutterwave is not configured. Add keys under Admin → Settings → Payments.');
        }

        try {
            $response = Http::withToken($secret)->acceptJson()->timeout(20)->{$method}(self::BASE_URL.$path, $data);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach Flutterwave. Please try again in a moment.');
        }

        $body = $response->json() ?? [];
        if (! $response->successful() || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? 'Flutterwave request failed ('.$response->status().').'));
        }

        return $body;
    }
}
