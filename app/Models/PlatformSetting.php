<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PlatformSetting extends Model
{
    protected $table = 'platform_settings';

    public static $snakeAttributes = false;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $primaryKey = 'key';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    public static function read(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('platform_settings')) {
            return $default;
        }

        $setting = static::query()->find($key);

        return $setting ? ($setting->value['data'] ?? $default) : $default;
    }

    public static function write(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => ['data' => $value]]);
    }

    /**
     * Addresses that receive a copy of every applicant email.
     *
     * @return list<string>
     */
    public static function adminNotifyEmails(): array
    {
        $emails = static::read('adminNotifyEmails');
        if (! is_array($emails)) {
            $fallback = trim((string) config('services.zeptomail.review'));

            return $fallback !== '' ? [$fallback] : [];
        }

        return array_values(array_filter(array_map(fn ($email) => strtolower(trim((string) $email)), $emails)));
    }

    /**
     * Contact details shown on the public help page.
     *
     * @return array{email: string, phone: ?string, whatsapp: ?string, hours: ?string, address: ?string, directions: ?string, mapUrl: ?string}
     */
    public static function supportContact(): array
    {
        $saved = static::read('supportContact');
        $saved = is_array($saved) ? $saved : [];

        return [
            'email' => (string) ($saved['email'] ?? '') ?: 'hello@salesdock.ng',
            'phone' => $saved['phone'] ?? null,
            'whatsapp' => $saved['whatsapp'] ?? null,
            'hours' => array_key_exists('hours', $saved) ? $saved['hours'] : 'Monday to Friday, 9am to 5pm (WAT)',
            'address' => $saved['address'] ?? null,
            'directions' => $saved['directions'] ?? null,
            'mapUrl' => $saved['mapUrl'] ?? null,
        ];
    }

    /**
     * Active payment provider, platform fee, and API credentials.
     * Admin settings win; .env values are the bootstrap fallback.
     *
     * @return array{
     *     provider: string,
     *     platform_split: float,
     *     flutterwave: array{public_key: string, secret_key: string, secret_hash: string},
     *     paystack: array{public_key: string, secret_key: string}
     * }
     */
    public static function payment(): array
    {
        $saved = static::read('payment');
        $saved = is_array($saved) ? $saved : [];
        $provider = strtolower(trim((string) ($saved['provider'] ?? config('services.payments.provider', 'flutterwave'))));
        if (! in_array($provider, ['flutterwave', 'paystack'], true)) {
            $provider = 'flutterwave';
        }

        $split = (float) ($saved['platform_split'] ?? config('services.payments.platform_split', 0.015));
        $split = max(0.0, min(0.5, $split));

        $fw = is_array($saved['flutterwave'] ?? null) ? $saved['flutterwave'] : [];
        $ps = is_array($saved['paystack'] ?? null) ? $saved['paystack'] : [];

        return [
            'provider' => $provider,
            'platform_split' => $split,
            'flutterwave' => [
                'public_key' => self::credential($fw['public_key'] ?? null, (string) config('services.flutterwave.public_key')),
                'secret_key' => self::credential($fw['secret_key'] ?? null, (string) config('services.flutterwave.secret_key')),
                'secret_hash' => self::credential($fw['secret_hash'] ?? null, (string) config('services.flutterwave.secret_hash')),
            ],
            'paystack' => [
                'public_key' => self::credential($ps['public_key'] ?? null, (string) config('services.paystack.public_key')),
                'secret_key' => self::credential($ps['secret_key'] ?? null, (string) config('services.paystack.secret_key')),
            ],
        ];
    }

    /**
     * Masked view of payment settings for the admin form (secrets show last 4 only).
     *
     * @return array{
     *     provider: string,
     *     platform_split_percent: float,
     *     flutterwave: array{public_key: string, secret_key_hint: string, secret_hash_hint: string, has_secret_key: bool, has_secret_hash: bool},
     *     paystack: array{public_key: string, secret_key_hint: string, has_secret_key: bool}
     * }
     */
    public static function paymentForm(): array
    {
        $payment = static::payment();

        return [
            'provider' => $payment['provider'],
            'platform_split_percent' => round($payment['platform_split'] * 100, 3),
            'flutterwave' => [
                'public_key' => $payment['flutterwave']['public_key'],
                'secret_key_hint' => self::maskSecret($payment['flutterwave']['secret_key']),
                'secret_hash_hint' => self::maskSecret($payment['flutterwave']['secret_hash']),
                'has_secret_key' => $payment['flutterwave']['secret_key'] !== '',
                'has_secret_hash' => $payment['flutterwave']['secret_hash'] !== '',
            ],
            'paystack' => [
                'public_key' => $payment['paystack']['public_key'],
                'secret_key_hint' => self::maskSecret($payment['paystack']['secret_key']),
                'has_secret_key' => $payment['paystack']['secret_key'] !== '',
            ],
        ];
    }

    /**
     * @param  array{
     *     provider: string,
     *     platform_split: float,
     *     flutterwave?: array{public_key?: ?string, secret_key?: ?string, secret_hash?: ?string},
     *     paystack?: array{public_key?: ?string, secret_key?: ?string}
     * }  $input
     */
    public static function writePayment(array $input): void
    {
        $current = static::read('payment');
        $current = is_array($current) ? $current : [];
        $currentFw = is_array($current['flutterwave'] ?? null) ? $current['flutterwave'] : [];
        $currentPs = is_array($current['paystack'] ?? null) ? $current['paystack'] : [];

        $provider = strtolower(trim((string) ($input['provider'] ?? 'flutterwave')));
        if (! in_array($provider, ['flutterwave', 'paystack'], true)) {
            $provider = 'flutterwave';
        }

        $split = max(0.0, min(0.5, (float) ($input['platform_split'] ?? 0.015)));
        $fwIn = is_array($input['flutterwave'] ?? null) ? $input['flutterwave'] : [];
        $psIn = is_array($input['paystack'] ?? null) ? $input['paystack'] : [];

        static::write('payment', [
            'provider' => $provider,
            'platform_split' => $split,
            'flutterwave' => [
                'public_key' => self::keepOrReplace($fwIn['public_key'] ?? null, (string) ($currentFw['public_key'] ?? '')),
                'secret_key' => self::keepOrReplace($fwIn['secret_key'] ?? null, (string) ($currentFw['secret_key'] ?? '')),
                'secret_hash' => self::keepOrReplace($fwIn['secret_hash'] ?? null, (string) ($currentFw['secret_hash'] ?? '')),
            ],
            'paystack' => [
                'public_key' => self::keepOrReplace($psIn['public_key'] ?? null, (string) ($currentPs['public_key'] ?? '')),
                'secret_key' => self::keepOrReplace($psIn['secret_key'] ?? null, (string) ($currentPs['secret_key'] ?? '')),
            ],
        ]);
    }

    /**
     * Zoho SalesIQ WhatsApp outbound credentials and template ids.
     *
     * @return array{
     *     enabled: bool,
     *     accounts_url: string,
     *     api_base: string,
     *     client_id: string,
     *     client_secret: string,
     *     refresh_token: string,
     *     access_token: string,
     *     access_token_expires_at: int,
     *     screen_name: string,
     *     app_id: string,
     *     department_id: string,
     *     otp_template_id: string,
     *     restock_template_id: string,
     *     purchase_order_template_id: string,
     *     salesiq_widget_code: string
     * }
     */
    public static function zohoWhatsApp(): array
    {
        $saved = static::read('zohoWhatsApp');
        $saved = is_array($saved) ? $saved : [];

        return [
            'enabled' => (bool) ($saved['enabled'] ?? config('services.zoho_whatsapp.enabled', false)),
            'accounts_url' => self::credential($saved['accounts_url'] ?? null, (string) config('services.zoho_whatsapp.accounts_url', 'https://accounts.zoho.com')),
            'api_base' => self::credential($saved['api_base'] ?? null, (string) config('services.zoho_whatsapp.api_base', 'https://salesiq.zoho.com')),
            'client_id' => self::credential($saved['client_id'] ?? null, (string) config('services.zoho_whatsapp.client_id')),
            'client_secret' => self::credential($saved['client_secret'] ?? null, (string) config('services.zoho_whatsapp.client_secret')),
            'refresh_token' => self::credential($saved['refresh_token'] ?? null, (string) config('services.zoho_whatsapp.refresh_token')),
            'access_token' => trim((string) ($saved['access_token'] ?? '')),
            'access_token_expires_at' => (int) ($saved['access_token_expires_at'] ?? 0),
            'screen_name' => self::credential($saved['screen_name'] ?? null, (string) config('services.zoho_whatsapp.screen_name')),
            'app_id' => self::credential($saved['app_id'] ?? null, (string) config('services.zoho_whatsapp.app_id')),
            'department_id' => self::credential($saved['department_id'] ?? null, (string) config('services.zoho_whatsapp.department_id')),
            'otp_template_id' => self::credential($saved['otp_template_id'] ?? null, (string) config('services.zoho_whatsapp.otp_template_id')),
            'restock_template_id' => self::credential($saved['restock_template_id'] ?? null, (string) config('services.zoho_whatsapp.restock_template_id')),
            'purchase_order_template_id' => self::credential($saved['purchase_order_template_id'] ?? null, (string) config('services.zoho_whatsapp.purchase_order_template_id')),
            'salesiq_widget_code' => trim((string) ($saved['salesiq_widget_code'] ?? config('services.zoho_whatsapp.salesiq_widget_code', ''))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function zohoWhatsAppForm(): array
    {
        $cfg = static::zohoWhatsApp();

        return [
            'enabled' => $cfg['enabled'],
            'accounts_url' => $cfg['accounts_url'],
            'api_base' => $cfg['api_base'],
            'client_id' => $cfg['client_id'],
            'client_secret_hint' => self::maskSecret($cfg['client_secret']),
            'has_client_secret' => $cfg['client_secret'] !== '',
            'refresh_token_hint' => self::maskSecret($cfg['refresh_token']),
            'has_refresh_token' => $cfg['refresh_token'] !== '',
            'screen_name' => $cfg['screen_name'],
            'app_id' => $cfg['app_id'],
            'department_id' => $cfg['department_id'],
            'otp_template_id' => $cfg['otp_template_id'],
            'restock_template_id' => $cfg['restock_template_id'],
            'purchase_order_template_id' => $cfg['purchase_order_template_id'],
            'salesiq_widget_code' => $cfg['salesiq_widget_code'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function writeZohoWhatsApp(array $input): void
    {
        $current = static::read('zohoWhatsApp');
        $current = is_array($current) ? $current : [];

        static::write('zohoWhatsApp', [
            'enabled' => (bool) ($input['enabled'] ?? false),
            'accounts_url' => self::keepOrReplace($input['accounts_url'] ?? null, (string) ($current['accounts_url'] ?? '')),
            'api_base' => self::keepOrReplace($input['api_base'] ?? null, (string) ($current['api_base'] ?? '')),
            'client_id' => self::keepOrReplace($input['client_id'] ?? null, (string) ($current['client_id'] ?? '')),
            'client_secret' => self::keepOrReplace($input['client_secret'] ?? null, (string) ($current['client_secret'] ?? '')),
            'refresh_token' => self::keepOrReplace($input['refresh_token'] ?? null, (string) ($current['refresh_token'] ?? '')),
            'access_token' => (string) ($current['access_token'] ?? ''),
            'access_token_expires_at' => (int) ($current['access_token_expires_at'] ?? 0),
            'screen_name' => self::keepOrReplace($input['screen_name'] ?? null, (string) ($current['screen_name'] ?? '')),
            'app_id' => self::keepOrReplace($input['app_id'] ?? null, (string) ($current['app_id'] ?? '')),
            'department_id' => self::keepOrReplace($input['department_id'] ?? null, (string) ($current['department_id'] ?? '')),
            'otp_template_id' => self::keepOrReplace($input['otp_template_id'] ?? null, (string) ($current['otp_template_id'] ?? '')),
            'restock_template_id' => self::keepOrReplace($input['restock_template_id'] ?? null, (string) ($current['restock_template_id'] ?? '')),
            'purchase_order_template_id' => self::keepOrReplace($input['purchase_order_template_id'] ?? null, (string) ($current['purchase_order_template_id'] ?? '')),
            'salesiq_widget_code' => array_key_exists('salesiq_widget_code', $input)
                ? trim((string) $input['salesiq_widget_code'])
                : (string) ($current['salesiq_widget_code'] ?? ''),
        ]);
    }

    public static function writeZohoAccessToken(string $token, int $expiresAt): void
    {
        $current = static::read('zohoWhatsApp');
        $current = is_array($current) ? $current : [];
        $current['access_token'] = $token;
        $current['access_token_expires_at'] = $expiresAt;
        static::write('zohoWhatsApp', $current);
    }

    /**
     * Google Analytics 4 measurement ID (G-XXXXXXXX), or empty when disabled.
     * Admin-saved values win (including an explicit blank to turn GA off); env is only the bootstrap fallback.
     */
    public static function googleAnalyticsId(): string
    {
        $saved = static::read('googleAnalytics');
        if (is_array($saved) && array_key_exists('measurement_id', $saved)) {
            $id = strtoupper(trim((string) $saved['measurement_id']));

            return preg_match('/^G-[A-Z0-9]+$/', $id) ? $id : '';
        }

        $id = strtoupper(trim((string) config('services.google_analytics.measurement_id', '')));

        return preg_match('/^G-[A-Z0-9]+$/', $id) ? $id : '';
    }

    public static function writeGoogleAnalytics(?string $measurementId): void
    {
        $id = strtoupper(trim((string) $measurementId));
        static::write('googleAnalytics', [
            'measurement_id' => preg_match('/^G-[A-Z0-9]+$/', $id) ? $id : '',
        ]);
    }

    private static function credential(?string $saved, string $fallback): string
    {
        $saved = trim((string) $saved);

        return $saved !== '' ? $saved : trim($fallback);
    }

    private static function keepOrReplace(?string $incoming, string $existing): string
    {
        $incoming = trim((string) $incoming);

        return $incoming !== '' ? $incoming : trim($existing);
    }

    private static function maskSecret(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= 4) {
            return str_repeat('•', strlen($value));
        }

        return str_repeat('•', max(8, strlen($value) - 4)).substr($value, -4);
    }
}
