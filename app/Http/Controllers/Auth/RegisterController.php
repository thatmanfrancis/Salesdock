<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Services\ZohoWhatsApp;
use App\Support\AuthMail;
use App\Support\PhoneNumber;
use App\Support\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        Turnstile::verify($request, 'signup');

        $data = $request->validate([
            'businessName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
        ]);

        $email = strtolower(trim($data['email']));
        $businessName = trim($data['businessName']);
        $phone = PhoneNumber::e164($data['phone']);
        if ($phone === null) {
            return back()->withInput()->withErrors(['phone' => 'Enter a valid WhatsApp number, e.g. 0803 000 0000.']);
        }

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        if ($user) {
            $closed = $user->tenantId && Tenant::onlyTrashed()->whereKey($user->tenantId)->exists();

            return back()->withInput()->withErrors(['email' => $closed ? Tenant::CLOSED_MESSAGE : 'Email already registered']);
        }

        $existing = TenantRegistration::query()->whereRaw('lower(email) = ?', [$email])->first();
        if ($existing) {
            $live = Tenant::query()->whereRaw('lower(email) = ?', [$email])->exists();
            $canReuse = $existing->status === 'REJECTED'
                || ! $existing->emailVerified
                || (! $live && $existing->planSelected);
            if (! $canReuse) {
                return back()->withInput()->withErrors(['email' => 'Email already registered']);
            }
            $existing->delete();
        }

        [$token, $otp] = $this->issueVerification();

        TenantRegistration::query()->create([
            'ownerName' => $businessName,
            'email' => $email,
            'phone' => $phone,
            // The real password is chosen on the verification page; this placeholder can never match.
            'passwordHash' => Hash::make(Str::random(64), ['rounds' => 12]),
            'businessName' => $businessName,
            'verifyToken' => $token,
            'verifyTokenExp' => now()->addDay(),
            'verifyOtpHash' => Hash::make($otp),
        ]);

        $this->sendVerification($email, $businessName, $token, $otp, $phone);

        return redirect()->route('register')->with([
            'sent' => $email,
            'sent_phone' => $phone,
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));
        $registration = TenantRegistration::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->where('emailVerified', false)
            ->first();

        if ($registration) {
            [$token, $otp] = $this->issueVerification();
            $registration->forceFill([
                'verifyToken' => $token,
                'verifyTokenExp' => now()->addDay(),
                'verifyOtpHash' => Hash::make($otp),
            ])->save();

            $this->sendVerification(
                $registration->email,
                $registration->businessName,
                $token,
                $otp,
                (string) $registration->phone
            );
        }

        return redirect()->route('login')->with('status', 'If that signup is still waiting, a new verification link and WhatsApp code are on their way to '.$email.'.');
    }

    public function verifyOtp(Request $request): RedirectResponse|View
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $email = strtolower(trim($data['email']));
        $otp = preg_replace('/\D+/', '', $data['otp']) ?? '';
        $registration = TenantRegistration::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->where('emailVerified', false)
            ->first();

        if (! $registration || ! $registration->verifyOtpHash || ! Hash::check($otp, $registration->verifyOtpHash)) {
            return back()->withInput()->with([
                'sent' => $email,
                'sent_phone' => $registration?->phone,
            ])->withErrors(['otp' => 'That code is not valid. Check WhatsApp or request a new code.']);
        }

        if ($registration->verifyTokenExp && $registration->verifyTokenExp->isPast()) {
            return back()->withInput()->with([
                'sent' => $email,
                'sent_phone' => $registration->phone,
            ])->withErrors(['otp' => 'That code has expired. Request a new verification below.']);
        }

        return redirect()->route('verify-email', ['token' => $registration->verifyToken]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function issueVerification(): array
    {
        return [Str::random(64), (string) random_int(100000, 999999)];
    }

    private function sendVerification(string $email, string $businessName, string $token, string $otp, string $phone): void
    {
        AuthMail::send($email, $businessName, 'Verify your email — SalesDock', [
            'preheader' => 'Confirm your email to open your SalesDock dashboard.',
            'heading' => 'Verify your email address',
            'kicker' => "You're almost there.",
            'paragraphs' => [
                'Hi <strong style="color:#111827;">'.e($businessName).'</strong>, please verify your email address to open the SalesDock dashboard.',
                'Use the button below to confirm this address and set your password. We also sent a 6-digit code to your WhatsApp: <strong style="color:#111827;">'.e($otp).'</strong>.',
            ],
            'url' => route('verify-email', ['token' => $token]),
            'label' => 'Verify email & set password',
            'stepsTitle' => 'What happens next',
            'steps' => [
                'Open the verification link, or enter the WhatsApp code on the signup page. Both expire in 24 hours.',
                'Choose a password and confirm it.',
                'Complete your business details. Every feature is free for '.TenantProvisioner::TRIAL_DAYS.' days.',
            ],
            'note' => 'If you did not sign up for SalesDock, you can ignore this email.',
        ], copyAdmins: true);

        if ($phone !== '') {
            app(ZohoWhatsApp::class)->sendOtp($phone, $otp, $businessName);
        }
    }
}
