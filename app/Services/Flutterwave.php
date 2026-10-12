<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Flutterwave v3 Standard (hosted checkout): the server creates a payment link, the customer pays on
 * Flutterwave's page, and every result is verified server-side before anything is marked paid.
 */
class Flutterwave
{
    private const BASE_URL = 'https://api.flutterwave.com/v3';

    /** Separates our invoice/order reference from the per-attempt suffix in tx_ref. */
    private const ATTEMPT_SEPARATOR = '__';

    public function ready(): bool
    {
        return $this->secretKey() !== null;
    }

    public function publicKey(): ?string
    {
        $key = trim((string) config('services.flutterwave.public_key'));

        return preg_match('/^FLWPUBK(?:_(?:TEST|LIVE))?-.+-X$/', $key) ? $key : null;
    }

    public function secretKey(): ?string
    {
        $key = trim((string) config('services.flutterwave.secret_key'));

        return preg_match('/^FLWSECK(?:_(?:TEST|LIVE))?-.+-X$/', $key) ? $key : null;
    }

    /**
     * A fresh tx_ref for each payment attempt; Flutterwave refuses a reference it has already seen.
     */
    public function attemptReference(string $reference): string
    {
        return $reference.self::ATTEMPT_SEPARATOR.strtoupper(Str::random(6));
    }

    /**
     * Our invoice number or order reference behind a tx_ref (also accepts references without a suffix).
     */
    public static function baseReference(string $txRef): string
    {
        return explode(self::ATTEMPT_SEPARATOR, $txRef, 2)[0];
    }

    /**
     * Creates a hosted payment link.
     *
     * @param  array{reference: string, amount: float, email: string, name?: ?string, phone?: ?string, redirect: string, title: string, description: string}  $payment
     */
    public function checkoutLink(array $payment): string
    {
        $body = $this->request('post', '/payments', [
            'tx_ref' => $this->attemptReference($payment['reference']),
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
        ]);

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

        return $this->request('get', '/transactions/'.$transactionId.'/verify')['data'] ?? [];
    }

    public function verifyByReference(string $txRef): array
    {
        return $this->request('get', '/transactions/verify_by_reference', ['tx_ref' => $txRef])['data'] ?? [];
    }

    public function successful(?string $status): bool
    {
        return in_array(strtolower((string) $status), ['successful', 'success', 'completed'], true);
    }

    /**
     * True only when Flutterwave confirms a successful NGN payment of at least the expected amount for this reference.
     */
    public function confirms(array $payment, string $reference, float $expectedAmount): bool
    {
        return $this->successful($payment['status'] ?? null)
            && self::baseReference((string) ($payment['tx_ref'] ?? '')) === $reference
            && strtoupper((string) ($payment['currency'] ?? '')) === 'NGN'
            && (float) ($payment['amount'] ?? 0) + 0.009 >= $expectedAmount;
    }

    public function webhookTrusted(?string $header): bool
    {
        $secret = trim((string) config('services.flutterwave.secret_hash'));
        if ($secret === '' || $header === null || strlen($header) !== strlen($secret)) {
            return false;
        }

        return hash_equals($secret, $header);
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $secret = $this->secretKey();
        if (! $secret) {
            throw new RuntimeException('Flutterwave is not configured. Set FLUTTERWAVE_SECRET_KEY.');
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
