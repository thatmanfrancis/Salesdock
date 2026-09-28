<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class Flutterwave
{
    public function ready(): bool
    {
        return $this->publicKey() !== null && $this->secretKey() !== null;
    }

    public function publicKey(): ?string
    {
        $key = trim((string) env('FLUTTERWAVE_PUBLIC_KEY', ''));

        return preg_match('/^FLWPUBK(?:_(?:TEST|LIVE))?-.+-X$/', $key) ? $key : null;
    }

    public function secretKey(): ?string
    {
        $key = trim((string) env('FLUTTERWAVE_SECRET_KEY', ''));

        return preg_match('/^FLWSECK(?:_(?:TEST|LIVE))?-.+-X$/', $key) ? $key : null;
    }

    public function verify(string $txRef): array
    {
        $secret = $this->secretKey();
        if (! $secret) {
            throw new RuntimeException('Flutterwave Secret Key is missing. Set FLUTTERWAVE_SECRET_KEY to a v3 key.');
        }

        $response = Http::withToken($secret)->acceptJson()->get('https://api.flutterwave.com/v3/transactions/verify_by_reference', [
            'tx_ref' => $txRef,
        ]);

        $body = $response->json();
        if (! $response->ok() || empty($body['data'])) {
            throw new RuntimeException($body['message'] ?? 'Unable to verify Flutterwave transaction');
        }

        return $body['data'];
    }

    public function successful(?string $status): bool
    {
        return in_array(strtolower((string) $status), ['successful', 'success', 'completed'], true);
    }

    public function webhookTrusted(?string $header): bool
    {
        $secret = trim((string) env('FLUTTERWAVE_SECRET_HASH', ''));
        if ($secret === '' || $header === null || strlen($header) !== strlen($secret)) {
            return false;
        }

        return hash_equals($secret, $header);
    }
}
