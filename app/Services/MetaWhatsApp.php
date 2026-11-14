<?php

namespace App\Services;

use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsApp
{
    public function canSendOtp(): bool
    {
        return config('services.meta_whatsapp.enabled')
            && ctype_digit((string) config('services.meta_whatsapp.phone_number_id'))
            && filled(config('services.meta_whatsapp.access_token'))
            && filled(config('services.meta_whatsapp.otp_template'));
    }

    public function sendOtp(string $phone, string $code): bool
    {
        return $this->canSendOtp() && $this->sendTemplate($phone, (string) config('services.meta_whatsapp.otp_template'), [$code], $code);
    }

    /**
     * @param  array<string, string>  $placeholders  Ordered to match the template's positional parameters.
     */
    public function sendTemplate(string $phone, string $template, array $placeholders, ?string $copyCode = null): bool
    {
        $config = config('services.meta_whatsapp');
        $recipient = PhoneNumber::waMe($phone);
        $sender = (string) $config['phone_number_id'];
        $version = (string) $config['api_version'];
        if (! $recipient || ! ctype_digit($sender) || ! preg_match('/^v\d+\.\d+$/', $version)
            || blank($config['access_token']) || blank($template)) {
            Log::warning('Meta WhatsApp template send skipped due to missing or invalid configuration.');

            return false;
        }

        $parameters = [];
        foreach ($placeholders as $value) {
            $text = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
            $parameters[] = ['type' => 'text', 'text' => $text !== '' ? $text : '—'];
        }
        $components = [['type' => 'body', 'parameters' => $parameters]];
        if ($copyCode !== null) {
            $components[] = [
                'type' => 'button', 'sub_type' => 'url', 'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $copyCode]],
            ];
        }

        try {
            $response = Http::withToken((string) $config['access_token'])
                ->acceptJson()->connectTimeout(5)->timeout(15)
                ->post("https://graph.facebook.com/{$version}/{$sender}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipient,
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => $config['language']],
                        'components' => $components,
                    ],
                ]);
        } catch (ConnectionException) {
            Log::warning('Meta WhatsApp request timed out or could not connect.');

            return false;
        }

        if (! $response->successful() || blank($response->json('messages.0.id'))) {
            Log::warning('Meta WhatsApp rejected a template message.', [
                'status' => $response->status(),
                'code' => $response->json('error.code'),
            ]);

            return false;
        }

        return true;
    }
}
