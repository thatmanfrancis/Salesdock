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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        ]);

        $email = strtolower(trim($data['email']));
        $businessName = trim($data['businessName']);

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

        $token = Str::random(64);
        TenantRegistration::query()->create([
            'ownerName' => $businessName,
            'email' => $email,
            // The real password is chosen on the verification page; this placeholder can never match.
            'passwordHash' => Hash::make(Str::random(64), ['rounds' => 12]),
            'businessName' => $businessName,
            'verifyToken' => $token,
            'verifyTokenExp' => now()->addDay(),
        ]);

        $this->sendVerification($email, $businessName, $token);

        return redirect()->route('register')->with('sent', $email);
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
            $token = Str::random(64);
            $registration->forceFill([
                'verifyToken' => $token,
                'verifyTokenExp' => now()->addDay(),
            ])->save();

            $this->sendVerification($registration->email, $registration->businessName, $token);
        }

        return redirect()->route('login')->with('status', 'If that signup is still waiting, a new verification link is on its way to '.$email.'.');
    }

    private function sendVerification(string $email, string $businessName, string $token): void
    {
        AuthMail::send($email, $businessName, 'Verify your email — SalesDock', [
            'preheader' => 'Confirm your email to open your SalesDock dashboard.',
            'heading' => 'Verify your email address',
            'kicker' => "You're almost there.",
            'paragraphs' => [
                'Hi <strong style="color:#111827;">'.e($businessName).'</strong>, please verify your email address to open the SalesDock dashboard.',
                'Use the button below to confirm this address and set your password.',
            ],
            'url' => route('verify-email', ['token' => $token]),
            'label' => 'Verify email & set password',
            'stepsTitle' => 'What happens next',
            'steps' => [
                'Open the verification link. It expires in 24 hours.',
                'Choose a password and confirm it.',
                'Complete your business details. Every feature is free for '.TenantProvisioner::TRIAL_DAYS.' days.',
            ],
            'note' => 'If you did not sign up for SalesDock, you can ignore this email.',
        ], copyAdmins: true);
    }
}
