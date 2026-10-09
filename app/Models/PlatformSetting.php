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
}
