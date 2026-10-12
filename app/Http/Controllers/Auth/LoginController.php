<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Support\Password;
use App\Support\Totp;
use App\Support\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create(Request $request)
    {
        if ($request->boolean('fresh')) {
            $request->session()->forget(['pending_2fa', 'pin_change_user']);
        }

        return view('auth.login');
    }

    public function store(Request $request)
    {
        if ($request->session()->has('pin_change_user') && $request->filled('new_pin')) {
            return $this->changePin($request);
        }

        if ($request->input('method') === 'pin') {
            return $this->pin($request);
        }

        if ($request->session()->has('pending_2fa')) {
            return $this->confirmTwoFa($request);
        }

        Turnstile::verify($request, 'login');

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($data['email']));
        $lockouts = [
            ['login-fail:'.$email.'|'.$request->ip(), 5, 900],
            ['login-fail-account:'.$email, 20, 3600],
        ];
        $this->guardLockouts($request, $lockouts, 'email', 'password');

        $user = User::query()->with('role')->whereRaw('lower(email) = ?', [$email])->first();
        $passwordMatches = $user && Password::check($data['password'], $user->passwordHash);

        if (! $user || ! $passwordMatches) {
            $registration = TenantRegistration::query()->whereRaw('lower(email) = ?', [$email])->first();
            if ($registration && (! $registration->emailVerified || Password::check($data['password'], $registration->passwordHash))) {
                return $this->registrationHold($registration);
            }

            $this->recordFailure($lockouts);

            throw ValidationException::withMessages(['email' => 'Those credentials do not match our records.']);
        }

        $this->clearLockouts($lockouts);

        if (! $user->isActive) {
            throw ValidationException::withMessages(['email' => 'This account is inactive. Ask the store owner to turn it back on.']);
        }

        if ($user->tenantId && ! $user->isSuperAdmin && Tenant::onlyTrashed()->whereKey($user->tenantId)->exists()) {
            throw ValidationException::withMessages(['email' => Tenant::CLOSED_MESSAGE]);
        }

        if (! str_starts_with((string) $user->passwordHash, '$2y$')) {
            $user->passwordHash = Hash::make($data['password'], ['rounds' => 12]);
        }

        if ($user->twoFaEnabled && $user->twoFaSecret) {
            $request->session()->put('pending_2fa', $user->id);

            return back()->withInput();
        }

        return $this->completeLogin($request, $user);
    }

    private function registrationHold(TenantRegistration $registration)
    {
        if (! $registration->emailVerified) {
            session()->flash('resend_email', $registration->email);

            throw ValidationException::withMessages([
                'email' => 'Verify your email first. Open the link we sent to your inbox to set your password.',
            ]);
        }

        if ($registration->status === 'REJECTED') {
            $reason = trim((string) $registration->rejectionReason);
            throw ValidationException::withMessages([
                'email' => $reason !== ''
                    ? "This application was not approved. {$reason}"
                    : 'This application was not approved. You can register again with the same email.',
            ]);
        }

        if ($registration->status === 'APPROVED' && $registration->planToken && $registration->planTokenExp?->isFuture()) {
            return redirect()->route('select-plan', ['token' => $registration->planToken]);
        }

        if ($registration->status === 'APPROVED') {
            throw ValidationException::withMessages([
                'email' => 'Your application is approved. The plan link has expired, so ask us to send a new one before signing in.',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => 'Your email is verified, but the account is still being set up. Contact support if this continues.',
        ]);
    }

    private function confirmTwoFa(Request $request)
    {
        $code = implode('', array_map('strval', (array) $request->input('otp', [])));
        if ($code === '') {
            $code = (string) $request->input('two_fa_code');
        }
        $request->merge(['two_fa_code' => $code]);
        $data = $request->validate([
            'two_fa_code' => ['required', 'digits:6'],
        ], [
            'two_fa_code.required' => 'Enter the 6-digit code from your authenticator app.',
            'two_fa_code.digits' => 'Enter the 6-digit code from your authenticator app.',
        ]);
        $userId = (string) $request->session()->get('pending_2fa');
        $lockouts = [['twofa-fail:'.$userId, 5, 900]];
        if ($this->lockedFor($lockouts) > 0) {
            $request->session()->forget('pending_2fa');
        }
        $this->guardLockouts($request, $lockouts, 'two_fa_code', 'two-factor');

        $user = User::query()->with('role')->find($userId);

        if (! $user || ! Totp::verify($user->twoFaSecret, $data['two_fa_code'])) {
            $this->recordFailure($lockouts);

            throw ValidationException::withMessages(['two_fa_code' => 'That authentication code is invalid.']);
        }

        $this->clearLockouts($lockouts);
        $request->session()->forget('pending_2fa');

        return $this->completeLogin($request, $user);
    }

    private function completeLogin(Request $request, User $user)
    {
        $lastSeenAt = $user->lastLoginAt;
        $user->forceFill(['lastLoginAt' => now()])->save();
        Auth::login($user);
        $request->session()->regenerate();

        $daysAway = $lastSeenAt ? (int) floor($lastSeenAt->diffInDays(now(), true)) : 0;
        if (! $user->isSuperAdmin && $daysAway >= 7) {
            $request->session()->put('away_prompt', [
                'days' => $daysAway,
                'lastSeenAt' => $lastSeenAt->toIso8601String(),
            ]);

            return redirect()->route('welcome-back');
        }

        return redirect()->intended($user->homePath());
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function pin(Request $request)
    {
        if ($request->filled('new_pin')) {
            return $this->changePin($request);
        }

        Turnstile::verify($request, 'login');

        $data = $request->validate([
            'pin' => ['required', 'digits_between:4,6'],
        ]);

        $slug = trim((string) ($request->input('slug') ?: $request->query('slug')));
        if ($slug === '') {
            throw ValidationException::withMessages([
                'pin' => 'We could not detect your store automatically. Open login from your store domain or pass ?slug=your-store-slug.',
            ]);
        }

        $store = mb_strtolower($slug);
        $lockouts = [
            ['pin-fail:'.$store.'|'.$request->ip(), 5, 900],
            ['pin-fail-store:'.$store, 30, 3600],
        ];
        $this->guardLockouts($request, $lockouts, 'pin', 'PIN');

        $tenant = Tenant::query()->where('slug', $slug)->first();
        $user = $tenant
            ? User::query()->with('role')->where('tenantId', $tenant->id)->where('pin', hash('sha256', $data['pin']))->where('isActive', true)->first()
            : null;

        if (! $user) {
            $this->recordFailure($lockouts);

            throw ValidationException::withMessages(['pin' => 'That PIN was not recognized for this store.']);
        }

        $this->clearLockouts($lockouts);

        if ($user->pinIsDefault) {
            $request->session()->put('pin_change_user', $user->id);

            return back()->withInput();
        }

        return $this->completeLogin($request, $user);
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int}>  $lockouts  [key, max failures, decay seconds]
     */
    private function guardLockouts(Request $request, array $lockouts, string $field, string $kind): void
    {
        $seconds = $this->lockedFor($lockouts);
        if ($seconds <= 0) {
            return;
        }

        Log::warning('Sign-in locked after repeated failures', ['kind' => $kind, 'ip' => $request->ip(), 'retryAfter' => $seconds]);
        $wait = $seconds >= 120 ? (int) ceil($seconds / 60).' minutes' : $seconds.' seconds';

        throw ValidationException::withMessages([
            $field => "Too many failed attempts. For your security, sign-in is paused for {$wait}.",
        ]);
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int}>  $lockouts
     */
    private function lockedFor(array $lockouts): int
    {
        $seconds = 0;
        foreach ($lockouts as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = max($seconds, RateLimiter::availableIn($key));
            }
        }

        return $seconds;
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int}>  $lockouts
     */
    private function recordFailure(array $lockouts): void
    {
        foreach ($lockouts as [$key, , $decay]) {
            RateLimiter::hit($key, $decay);
        }
    }

    /**
     * Clears the per-IP counters on success; account-wide counters keep running so a distributed attack still trips them.
     *
     * @param  list<array{0: string, 1: int, 2: int}>  $lockouts
     */
    private function clearLockouts(array $lockouts): void
    {
        RateLimiter::clear($lockouts[0][0]);
    }

    private function changePin(Request $request)
    {
        $data = $request->validate([
            'new_pin' => ['required', 'digits_between:4,6', 'confirmed'],
        ]);

        $user = User::query()->with('role')->find($request->session()->pull('pin_change_user'));
        if (! $user) {
            throw ValidationException::withMessages(['pin' => 'Start PIN sign-in again.']);
        }

        $user->forceFill([
            'pin' => hash('sha256', $data['new_pin']),
            'pinIsDefault' => false,
        ])->save();
        $request->session()->forget('pin_change_user');

        return $this->completeLogin($request, $user);
    }
}
