<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthMail
{
    /**
     * @param  bool  $copyAdmins  BCC the admin recipients from platform settings (applicant emails only).
     * @param  array{address?: string, name?: string}  $replyTo
     */
    public static function send(string $to, string $name, string $subject, array $mail, bool $copyAdmins = false, array $replyTo = []): bool
    {
        $key = trim((string) config('services.zeptomail.key'));
        $from = trim((string) config('services.zeptomail.from'));

        if ($key === '' || $from === '') {
            Log::error('zeptomail is not configured', ['to' => $to, 'subject' => $subject]);

            return false;
        }

        if (! str_starts_with($key, 'Zoho-enczapikey')) {
            $key = 'Zoho-enczapikey '.$key;
        }

        $mail = array_merge([
            'preheader' => $subject,
            'heading' => $subject,
            'kicker' => null,
            'paragraphs' => [],
            'summary' => [],
            'summaryTitle' => null,
            'steps' => [],
            'stepsTitle' => null,
            'url' => null,
            'label' => null,
            'note' => null,
            'email' => $to,
        ], $mail);

        $fromName = self::brandName((string) (config('services.zeptomail.name') ?: 'SalesDock'));
        $logo = self::inlineLogo();
        $html = view('emails.message', $mail)->render();
        $html = str_replace('SalesDocks', 'SalesDock', $html);

        $payload = [
            'from' => [
                'address' => $from,
                'name' => $fromName,
            ],
            'to' => [[
                'email_address' => [
                    'address' => $to,
                    'name' => $name,
                ],
            ]],
            'subject' => str_replace('SalesDocks', 'SalesDock', $subject),
            'htmlbody' => $html,
            'inline_images' => [$logo],
        ];

        if (filter_var($replyTo['address'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $payload['reply_to'] = [[
                'address' => $replyTo['address'],
                'name' => $replyTo['name'] ?? $replyTo['address'],
            ]];
        }

        if ($copyAdmins) {
            $bcc = collect(PlatformSetting::adminNotifyEmails())
                ->reject(fn (string $address) => strcasecmp($address, $to) === 0)
                ->unique()
                ->map(fn (string $address) => [
                    'email_address' => [
                        'address' => $address,
                        'name' => 'SalesDock admin',
                    ],
                ])
                ->values()
                ->all();
            if ($bcc !== []) {
                $payload['bcc'] = $bcc;
            }
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => $key,
            ])->post('https://api.zeptomail.com/v1.1/email', $payload);

            if ($response->failed()) {
                Log::error('zeptomail failed', [
                    'to' => $to,
                    'subject' => $subject,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return false;
            }
        } catch (\Throwable $e) {
            Log::error('zeptomail failed', ['to' => $to, 'subject' => $subject, 'error' => $e->getMessage()]);

            return false;
        }

        return true;
    }

    private static function brandName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || strcasecmp($name, 'SalesDocks') === 0) {
            return 'SalesDock';
        }

        return str_replace('SalesDocks', 'SalesDock', $name);
    }

    /**
     * Inline the same SalesDock mark used in the app header.
     * Email clients need a raster, so we send a PNG rendered from public/SalesDock.svg.
     *
     * @return array{content: string, mime_type: string, cid: string}
     */
    private static function inlineLogo(): array
    {
        $png = public_path('SalesDock-email.png');
        $svg = public_path('SalesDock.svg');
        $path = is_file($png) ? $png : $svg;
        $bytes = is_file($path) ? (string) file_get_contents($path) : '';
        $mime = str_ends_with(strtolower($path), '.png')
            ? 'image/png'
            : 'image/svg+xml';

        return [
            'content' => base64_encode($bytes),
            'mime_type' => $mime,
            'cid' => 'salesdock-logo',
        ];
    }
}
