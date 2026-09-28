<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthMail
{
    public static function send(string $to, string $name, string $subject, array $mail): void
    {
        $key = trim((string) config('services.zeptomail.key'));
        $from = trim((string) config('services.zeptomail.from'));

        if ($key === '' || $from === '') {
            Log::error('zeptomail is not configured', ['to' => $to, 'subject' => $subject]);

            return;
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

        $logo = file_get_contents(public_path('SalesDock.svg'));
        $html = view('emails.message', $mail)->render();
        $payload = [
            'from' => [
                'address' => $from,
                'name' => config('services.zeptomail.name') ?: 'SalesDock',
            ],
            'to' => [[
                'email_address' => [
                    'address' => $to,
                    'name' => $name,
                ],
            ]],
            'subject' => $subject,
            'htmlbody' => $html,
            'inline_images' => [[
                'mime_type' => 'image/svg+xml',
                'content' => base64_encode($logo ?: ''),
                'cid' => 'salesdock-logo',
            ]],
        ];

        $review = trim((string) config('services.zeptomail.review'));
        if ($review !== '' && strcasecmp($review, $to) !== 0) {
            $payload['bcc'] = [[
                'email_address' => [
                    'address' => $review,
                    'name' => 'SalesDock review',
                ],
            ]];
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => $key,
            ])->post('https://api.zeptomail.com/v1.1/email', $payload);

            if ($response->failed()) {
                Log::error('zeptomail failed', [
                    'to' => $to,
                    'subject' => $subject,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('zeptomail failed', ['to' => $to, 'subject' => $subject, 'error' => $e->getMessage()]);
        }
    }
}
