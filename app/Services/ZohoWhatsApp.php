<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Outbound WhatsApp via Zoho SalesIQ proactive templates (OTP, restock, purchase orders).
 */
class ZohoWhatsApp
{
    public function ready(): bool
    {
        $cfg = PlatformSetting::zohoWhatsApp();

        return $cfg['enabled']
            && $cfg['client_id'] !== ''
            && $cfg['client_secret'] !== ''
            && $cfg['refresh_token'] !== ''
            && $cfg['screen_name'] !== ''
            && $cfg['app_id'] !== ''
            && $cfg['department_id'] !== '';
    }

    public function sendOtp(string $phone, string $code, ?string $name = null): bool
    {
        $templateId = PlatformSetting::zohoWhatsApp()['otp_template_id'];
        if ($templateId === '') {
            return false;
        }

        return $this->sendTemplate($phone, $templateId, [
            'otp' => $code,
            '1' => $code,
            'code' => $code,
        ], $name, 'Signup verification');
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    public function sendRestockAlert(string $phone, array $placeholders, ?string $name = null): bool
    {
        $templateId = PlatformSetting::zohoWhatsApp()['restock_template_id'];
        if ($templateId === '') {
            return false;
        }

        return $this->sendTemplate($phone, $templateId, $placeholders, $name, 'Low stock alert');
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    public function sendPurchaseOrder(string $phone, array $placeholders, ?string $name = null): bool
    {
        $templateId = PlatformSetting::zohoWhatsApp()['purchase_order_template_id']
            ?: PlatformSetting::zohoWhatsApp()['restock_template_id'];
        if ($templateId === '') {
            return false;
        }

        return $this->sendTemplate($phone, $templateId, $placeholders, $name, 'Purchase order');
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    public function sendTemplate(string $phone, string $templateId, array $placeholders, ?string $name = null, string $title = 'SalesDock'): bool
    {
        if (! $this->ready()) {
            return false;
        }

        $e164 = PhoneNumber::e164($phone);
        if ($e164 === null) {
            Log::notice('Zoho WhatsApp skipped: invalid phone', ['phone' => $phone]);

            return false;
        }

        try {
            $cfg = PlatformSetting::zohoWhatsApp();
            $token = $this->accessToken();
            $url = rtrim($cfg['api_base'], '/').'/api/v2/'.rawurlencode($cfg['screen_name']).'/proactivemessages';

            $payload = [
                'app_id' => $cfg['app_id'],
                'department_id' => $cfg['department_id'],
                'channel' => 'whatsapp',
                'template_id' => $templateId,
                'title' => $title,
                'users' => [
                    'people' => [[
                        'phone_number' => $e164,
                        'first_name' => $name ?: 'Customer',
                    ]],
                ],
                'placeholders' => $placeholders,
            ];

            $response = Http::withToken($token, 'Zoho-oauthtoken')
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('Zoho WhatsApp send failed', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Zoho WhatsApp send error', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function accessToken(): string
    {
        $cfg = PlatformSetting::zohoWhatsApp();
        $expiresAt = (int) ($cfg['access_token_expires_at'] ?? 0);
        if ($cfg['access_token'] !== '' && $expiresAt > (time() + 60)) {
            return $cfg['access_token'];
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(5)
                ->timeout(15)
                ->post(rtrim($cfg['accounts_url'], '/').'/oauth/v2/token', [
                    'refresh_token' => $cfg['refresh_token'],
                    'client_id' => $cfg['client_id'],
                    'client_secret' => $cfg['client_secret'],
                    'grant_type' => 'refresh_token',
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach Zoho accounts to refresh the WhatsApp token.');
        }

        $body = $response->json() ?? [];
        $token = (string) ($body['access_token'] ?? '');
        if (! $response->successful() || $token === '') {
            throw new RuntimeException((string) ($body['error'] ?? 'Zoho OAuth refresh failed.'));
        }

        $expiresIn = (int) ($body['expires_in'] ?? 3600);
        PlatformSetting::writeZohoAccessToken($token, time() + max(60, $expiresIn));

        return $token;
    }
}
