<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Cloudflare Turnstile bot check. Off until both keys are set, so a missing key never locks people out.
 *
 * Passing the check once is remembered in the session for a few minutes and a few submissions, so a
 * mistyped password does not force the visitor to solve it again. Rate limits and lockouts still apply.
 */
class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const PASS_KEY = 'turnstile_pass';

    private const PASS_MINUTES = 30;

    private const PASS_USES = 10;

    public static function enabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret'));
    }

    public static function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    /**
     * Whether the visitor still has to solve the widget (false while a recent pass is still valid).
     */
    public static function needsChallenge(): bool
    {
        return self::enabled() && self::remainingPass() === null;
    }

    /**
     * Verifies the widget token posted with the form, or throws a validation error under `field`.
     */
    public static function verify(Request $request, string $action, string $field = 'captcha'): void
    {
        if (! self::enabled()) {
            return;
        }

        $pass = self::remainingPass();
        if ($pass !== null) {
            $pass['uses']--;
            $pass['uses'] > 0 ? session()->put(self::PASS_KEY, $pass) : session()->forget(self::PASS_KEY);

            return;
        }

        $fail = fn () => throw ValidationException::withMessages([
            $field => 'Please complete the security check and try again.',
        ]);

        $token = $request->input('cf-turnstile-response');
        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            Log::warning('Turnstile token missing from form', ['action' => $action, 'ip' => $request->ip()]);
            $fail();
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::VERIFY_URL, [
                'secret' => config('services.turnstile.secret'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
        } catch (Throwable $exception) {
            $response = null;
            Log::warning('Turnstile siteverify unreachable', ['error' => $exception->getMessage()]);
        }

        // A Cloudflare outage must not lock everyone out; rate limits and lockouts still guard these forms.
        if ($response === null || $response->serverError()) {
            Log::warning('Turnstile skipped: siteverify unavailable', ['action' => $action, 'status' => $response?->status()]);

            return;
        }

        $result = $response->json();
        if (! is_array($result) || ($result['success'] ?? false) !== true) {
            Log::warning('Turnstile check failed', ['action' => $action, 'ip' => $request->ip(), 'codes' => $result['error-codes'] ?? ['bad-response']]);
            $fail();
        }

        $tokenHostname = strtolower((string) ($result['hostname'] ?? ''));
        if (! in_array($tokenHostname, self::expectedHostnames($request), true)) {
            Log::warning('Turnstile token solved on another hostname', [
                'action' => $action,
                'tokenHostname' => $tokenHostname,
                'expected' => self::expectedHostnames($request),
            ]);
            $fail();
        }

        if (($result['action'] ?? $action) !== $action) {
            Log::info('Turnstile token action differs from form', ['action' => $action, 'tokenAction' => $result['action']]);
        }

        session()->put(self::PASS_KEY, [
            'until' => now()->addMinutes(self::PASS_MINUTES)->getTimestamp(),
            'uses' => self::PASS_USES,
        ]);
    }

    /**
     * @return array{until: int, uses: int}|null
     */
    private static function remainingPass(): ?array
    {
        $pass = session(self::PASS_KEY);
        if (! is_array($pass) || ($pass['uses'] ?? 0) < 1 || ($pass['until'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $pass;
    }

    /**
     * Hostnames a token may be solved on: the site being visited and APP_URL, plus TURNSTILE_HOSTNAMES.
     * Entries like "https://salesdock.ng/" are normalised and anything that is not a hostname is ignored
     * (and logged) so a typo cannot block every sign-in. Cloudflare's test keys report "example.com".
     *
     * @return list<string>
     */
    private static function expectedHostnames(Request $request): array
    {
        $hostnames = array_filter([
            strtolower($request->getHost()),
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            preg_match('/^[1-3]x0{10}/', (string) config('services.turnstile.site_key')) ? 'example.com' : null,
        ]);
        foreach (explode(',', (string) config('services.turnstile.hostnames')) as $entry) {
            $host = strtolower(trim($entry));
            if ($host === '') {
                continue;
            }
            $host = (string) (parse_url(str_contains($host, '://') ? $host : 'http://'.$host, PHP_URL_HOST) ?: '');
            if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) || preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $host)) {
                $hostnames[] = $host;
            } else {
                Log::error('TURNSTILE_HOSTNAMES has an entry that is not a hostname; ignoring it', ['entry' => $entry]);
            }
        }

        return array_values(array_unique($hostnames));
    }
}
