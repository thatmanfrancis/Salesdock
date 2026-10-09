<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Support\Password;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($data['email']));
        $user = User::query()->with('role')->whereRaw('lower(email) = ?', [$email])->first();
        $passwordMatches = $user && Password::check($data['password'], $user->passwordHash);

        if (! $user || ! $passwordMatches) {
            $registration = TenantRegistration::query()->whereRaw('lower(email) = ?', [$email])->first();
            if ($registration && (! $registration->emailVerified || Password::check($data['password'], $registration->passwordHash))) {
                return $this->registrationHold($registration);
            }

            throw ValidationException::withMessages(['email' => 'Those credentials do not match our records.']);
        }

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
        $user = User::query()->with('role')->find($request->session()->get('pending_2fa'));

        if (! $user || ! Totp::verify($user->twoFaSecret, $data['two_fa_code'])) {
            throw ValidationException::withMessages(['two_fa_code' => 'That authentication code is invalid.']);
        }

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

        $data = $request->validate([
            'pin' => ['required', 'digits_between:4,6'],
        ]);

        $slug = trim((string) ($request->input('slug') ?: $request->query('slug')));
        if ($slug === '') {
            throw ValidationException::withMessages([
                'pin' => 'We could not detect your store automatically. Open login from your store domain or pass ?slug=your-store-slug.',
            ]);
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();
        $user = $tenant
            ? User::query()->with('role')->where('tenantId', $tenant->id)->where('pin', hash('sha256', $data['pin']))->where('isActive', true)->first()
            : null;

        if (! $user) {
            throw ValidationException::withMessages(['pin' => 'That PIN was not recognized for this store.']);
        }

        if ($user->pinIsDefault) {
            $request->session()->put('pin_change_user', $user->id);

            return back()->withInput();
        }

        return $this->completeLogin($request, $user);
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
