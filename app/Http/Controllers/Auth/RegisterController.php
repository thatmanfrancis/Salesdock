<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\AuthMail;
use App\Support\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function verificationForm(Request $request): View
    {
        $pending = TenantRegistration::query()->find($request->session()->get('pending_registration'));
        if ($pending?->accountVerified()) {
            $pending = null;
        }

        return view('auth.verification', [
            'email' => $request->session()->get('verification_email', ''),
            'pending' => $pending,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Turnstile::verify($request, 'signup');
        $data = $request->validate([
            'businessName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);
        $email = strtolower(trim($data['email']));
        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()
            || Tenant::withTrashed()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'This email is already registered.']);
        }

        $registration = TenantRegistration::query()->where('email', $email)->first();
        if ($registration && ! $registration->accountVerified() && $registration->status !== 'REJECTED') {
            $request->session()->put('verification_email', $email);

            return redirect()->route('register.verification')->with('status', 'Continue your existing signup below.');
        }
        if ($registration?->status === 'REJECTED') {
            $registration->delete();
            $registration = null;
        }
        if ($registration) {
            throw ValidationException::withMessages(['email' => 'This email is already registered.']);
        }

        $registration = TenantRegistration::query()->create([
            'ownerName' => $data['businessName'], 'businessName' => $data['businessName'], 'email' => $email,
            'passwordHash' => Hash::make($data['password']),
            'verificationChannel' => 'email',
        ]);
        $request->session()->regenerate();
        $request->session()->put(['pending_registration' => $registration->id, 'verification_email' => $email]);

        return $this->sendCode($registration);
    }

    public function resend(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'max:72'],
        ]);
        $email = strtolower(trim($data['email']));
        $registration = TenantRegistration::query()->where('email', $email)->first();
        $request->session()->put('verification_email', $email);
        if (! $registration || $registration->accountVerified() || $registration->status === 'REJECTED') {
            return redirect()->route('register.verification')->with('status', 'If the signup details match, a new verification code will be sent.');
        }
        $sameBrowser = $request->session()->get('pending_registration') === $registration->id;
        if (! $sameBrowser && ! Hash::check($data['password'] ?? '', $registration->passwordHash)) {
            // Legacy signups have no chosen password. Email proof still opens their password setup page.
            if ($registration->verificationChannel !== null || isset($data['password'])) {
                return redirect()->route('register.verification')->with('status', 'If the signup details match, a new verification code will be sent.');
            }

            return $this->sendCode($registration, legacy: true);
        }
        $request->session()->regenerate();
        $request->session()->put('pending_registration', $registration->id);

        return $this->sendCode($registration);
    }

    public function verifyOtp(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'otp' => ['required', 'digits:6']]);
        $email = strtolower(trim($data['email']));
        $key = 'signup-code:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return $this->codeError('Too many attempts. Try again in a few minutes.');
        }
        RateLimiter::hit($key, 600);

        $result = DB::transaction(function () use ($email, $request, $data, $provisioner): User|RedirectResponse {
            $registration = TenantRegistration::query()->where('email', $email)->lockForUpdate()->first();
            if (! $registration || $registration->accountVerified() || $registration->status === 'REJECTED' || ! $registration->verifyOtpHash
                || ! Hash::check($data['otp'], $registration->verifyOtpHash)) {
                return $this->codeError('That code is not valid. Check the message or request a new code.');
            }
            if (! $registration->verifyTokenExp?->isFuture()) {
                return $this->codeError('That code has expired. Request a new one below.');
            }
            if ($registration->verificationChannel === null && $registration->verifyToken) {
                return redirect()->route('verify-email', ['token' => $registration->verifyToken]);
            }
            if ($request->session()->get('pending_registration') !== $registration->id) {
                return $this->codeError('Resume this signup using your password below, then enter the new code.');
            }
            if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()
                || Tenant::withTrashed()->whereRaw('lower(email) = ?', [$email])->exists()) {
                return $this->codeError('This email is already registered. Sign in to continue.');
            }
            $fromEmail = $registration->verificationChannel === 'email';
            $registration->forceFill([
                'emailVerified' => $fromEmail, 'emailVerifiedAt' => $fromEmail ? now() : null,
                'phoneVerifiedAt' => $fromEmail ? null : now(), 'status' => 'APPROVED', 'reviewedAt' => now(),
                'verifyOtpHash' => null, 'verifyToken' => null, 'verifyTokenExp' => null,
            ])->save();
            $tenant = $provisioner->provision($registration);

            return User::query()->where('tenantId', $tenant->id)->where('email', $email)->firstOrFail();
        });

        if ($result instanceof RedirectResponse) {
            return $result;
        }
        RateLimiter::clear($key);
        Auth::login($result);
        $request->session()->forget(['pending_registration', 'verification_email']);
        $request->session()->regenerate();

        return redirect($result->homePath());
    }

    private function codeError(string $message): RedirectResponse
    {
        return redirect()->route('register.verification')->withErrors(['otp' => $message]);
    }

    private function sendCode(TenantRegistration $registration, bool $legacy = false): RedirectResponse
    {
        $code = DB::transaction(function () use ($registration, $legacy): ?string {
            $pending = TenantRegistration::query()->lockForUpdate()->find($registration->id);
            if (! $pending || $pending->accountVerified() || $pending->verificationSentAt?->gt(now()->subMinute())) {
                return null;
            }
            do {
                $code = (string) random_int(100000, 999999);
            } while ($pending->verifyOtpHash && Hash::check($code, $pending->verifyOtpHash));
            $pending->update([
                'verificationChannel' => $legacy ? null : 'email', 'verifyToken' => $legacy ? Str::random(64) : null,
                'verifyOtpHash' => Hash::make($code), 'verifyTokenExp' => now()->addMinutes(10), 'verificationSentAt' => now(),
            ]);

            return $code;
        });
        if ($code === null) {
            return redirect()->route('register.verification')->with('status', 'If the signup details match, a new verification code will be sent.');
        }

        $accepted = AuthMail::send($registration->email, $registration->businessName, 'Your SalesDock verification code', [
            'paragraphs' => [
                'Your SalesDock verification code',
                '<strong style="display:block;text-align:center;padding:16px;background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;color:#14532d;font-size:32px;line-height:1.3;letter-spacing:5px;">'.$code.'</strong>',
                'Enter this code on the SalesDock verification page within 10 minutes.',
            ],
            'note' => 'If you did not request this code, ignore this message. Never share your code.',
        ]);

        return redirect()->route('register.verification')->with('status', ! $accepted && ! $legacy
            ? 'The email could not be sent. Try again in a minute.'
            : 'If the signup details match, a new verification code will be sent.');
    }
}
