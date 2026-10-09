<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\AuthMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class VerifyEmailController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $token = (string) $request->query('token', '');
        $registration = $this->registration($token);
        if (! $registration instanceof TenantRegistration) {
            return $registration;
        }

        return view('auth.set-password', [
            'token' => $token,
            'businessName' => $registration->businessName,
            'email' => $registration->email,
        ]);
    }

    public function store(Request $request, TenantProvisioner $provisioner): View|RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'The two passwords do not match.',
            'password.min' => 'Use at least 8 characters for the password.',
        ]);

        $registration = $this->registration($data['token']);
        if (! $registration instanceof TenantRegistration) {
            return $registration;
        }

        $registration->forceFill([
            'passwordHash' => Hash::make($data['password'], ['rounds' => 12]),
            'emailVerified' => true,
            'emailVerifiedAt' => now(),
            'status' => 'APPROVED',
            'reviewedAt' => now(),
        ])->save();

        $tenant = $provisioner->provision($registration);
        $owner = User::query()->where('tenantId', $tenant->id)->whereRaw('lower(email) = ?', [strtolower($registration->email)])->first();

        AuthMail::send($registration->email, $registration->ownerName, 'Welcome to SalesDock — complete your details', [
            'preheader' => 'Your email is verified and your password is set.',
            'heading' => 'Welcome to SalesDock',
            'kicker' => 'Your email is verified and your password is set.',
            'paragraphs' => [
                'Hi <strong style="color:#111827;">'.e($registration->ownerName).'</strong>, the SalesDock account for <strong style="color:#111827;">'.e($registration->businessName).'</strong> is ready.',
                'Every SalesDock feature is unlocked for the next <strong>'.TenantProvisioner::TRIAL_DAYS.' days</strong>. Complete your business details (owner name, phone, address and business type) to get started.',
                'Before the trial ends, choose a plan to keep selling without interruption. When the trial ends, the dashboard stays locked until a plan is paid for. Your data stays safe.',
            ],
            'summaryTitle' => 'Account summary',
            'summary' => [
                'Business' => $registration->businessName,
                'Email' => $registration->email,
                'Free trial' => TenantProvisioner::TRIAL_DAYS.' days, all features',
                'Status' => 'Complete your details',
            ],
            'url' => route('complete-profile'),
            'label' => 'Complete your details',
            'note' => 'Sign in with this email and the password you just set if the link asks you to.',
        ], copyAdmins: true);

        if (! $owner || ! $owner->isActive) {
            return redirect()->route('login')->with('status', 'Your email is verified. Sign in to continue.');
        }

        $owner->forceFill(['lastLoginAt' => now()])->save();
        Auth::login($owner);
        $request->session()->regenerate();

        return redirect($owner->homePath());
    }

    /**
     * Find a registration that can still be verified, or the response to show instead.
     */
    private function registration(string $token): TenantRegistration|View|RedirectResponse
    {
        if ($token === '') {
            return view('auth.verify', ['status' => 'error', 'message' => 'No token provided.']);
        }

        $registration = TenantRegistration::query()->where('verifyToken', $token)->first();
        if (! $registration) {
            return view('auth.verify', ['status' => 'error', 'message' => 'This verification link is not valid.']);
        }

        if ($registration->emailVerified) {
            return redirect()->route('login')->with('status', 'Your email is already verified. Sign in to continue.');
        }

        if ($registration->verifyTokenExp && $registration->verifyTokenExp->isPast()) {
            return view('auth.verify', [
                'status' => 'error',
                'message' => 'This verification link has expired. Send yourself a new one below.',
                'resendEmail' => $registration->email,
            ]);
        }

        return $registration;
    }
}
