<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthRateLimits();
    }

    /**
     * Strict limits on every sign-in, sign-up and account-recovery endpoint.
     * Failed-attempt lockouts per account and per store PIN live in the controllers.
     */
    private function configureAuthRateLimits(): void
    {
        RateLimiter::for('auth-login', function (Request $request) {
            $identity = $request->input('method') === 'pin'
                ? 'pin|'.mb_strtolower(trim((string) ($request->input('slug') ?: $request->query('slug'))))
                : 'email|'.mb_strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(10)->by('login-ip:'.$request->ip())->response($this->throttled($request)),
                Limit::perHour(60)->by('login-ip-hour:'.$request->ip())->response($this->throttled($request)),
                Limit::perMinute(5)->by('login-id:'.$identity.'|'.$request->ip())->response($this->throttled($request)),
            ];
        });

        RateLimiter::for('auth-register', fn (Request $request) => [
            Limit::perMinute(3)->by('register-ip:'.$request->ip())->response($this->throttled($request)),
            Limit::perHour(10)->by('register-ip-hour:'.$request->ip())->response($this->throttled($request)),
        ]);

        RateLimiter::for('auth-mail', fn (Request $request) => [
            Limit::perMinute(3)->by('mail-ip:'.$request->ip())->response($this->throttled($request)),
            Limit::perHour(10)->by('mail-ip-hour:'.$request->ip())->response($this->throttled($request)),
            Limit::perHour(3)->by('mail-email:'.mb_strtolower(trim((string) $request->input('email'))))->response($this->throttled($request)),
        ]);

        RateLimiter::for('auth-token', fn (Request $request) => [
            Limit::perMinute(5)->by('token-ip:'.$request->ip())->response($this->throttled($request)),
            Limit::perHour(30)->by('token-ip-hour:'.$request->ip())->response($this->throttled($request)),
        ]);

        RateLimiter::for('auth-session', fn (Request $request) => [
            Limit::perMinute(10)->by('session:'.($request->user()?->id ?: $request->ip()))->response($this->throttled($request)),
        ]);
    }

    /**
     * Sends the visitor back to the form with a readable message instead of a bare 429 page.
     *
     * @return callable(Request, array<string, string>): Response
     */
    private function throttled(Request $request): callable
    {
        return function (Request $request, array $headers): Response {
            $seconds = (int) ($headers['Retry-After'] ?? 60);
            $wait = $seconds >= 120 ? ceil($seconds / 60).' minutes' : $seconds.' seconds';
            $message = 'Too many attempts. Try again in '.$wait.'.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 429, $headers);
            }

            if (! $request->isMethod('POST')) {
                throw new ThrottleRequestsException($message, null, $headers);
            }

            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'pin', 'new_pin', 'new_pin_confirmation', 'otp', 'two_fa_code', 'token']))
                ->withErrors(['throttle' => $message])
                ->withHeaders($headers);
        };
    }
}
