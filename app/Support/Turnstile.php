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

    private const PASS_MINUTES = 10;

    private const PASS_USES = 5;

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
            $fail();
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::VERIFY_URL, [
                'secret' => config('services.turnstile.secret'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
            $result = $response->successful() ? $response->json() : null;
        } catch (Throwable $exception) {
            Log::warning('Turnstile siteverify unreachable', ['error' => $exception->getMessage()]);
            $result = null;
        }

        if (! is_array($result) || ($result['success'] ?? false) !== true) {
            Log::notice('Turnstile check failed', ['action' => $action, 'ip' => $request->ip(), 'codes' => $result['error-codes'] ?? ['no-response']]);
            $fail();
        }

        $hostnames = self::expectedHostnames();
        if ($hostnames !== []) {
            if (($result['action'] ?? null) !== $action || ! in_array(strtolower((string) ($result['hostname'] ?? '')), $hostnames, true)) {
                Log::notice('Turnstile token action or hostname mismatch', [
                    'action' => $action,
                    'tokenAction' => $result['action'] ?? null,
                    'tokenHostname' => $result['hostname'] ?? null,
                    'expected' => $hostnames,
                ]);
                $fail();
            }
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
     * TURNSTILE_HOSTNAMES as clean hostnames; entries like "https://salesdock.ng/" are normalised and
     * anything that is not a hostname is ignored (and logged) so a typo cannot block every sign-in.
     *
     * @return list<string>
     */
    private static function expectedHostnames(): array
    {
        $hostnames = [];
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
