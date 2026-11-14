<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetToken;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Support\AuthMail;
use App\Support\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function requestForm()
    {
        return view('auth.forgot');
    }

    public function send(Request $request)
    {
        Turnstile::verify($request, 'forgot-password');

        $data = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower(trim($data['email']));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        $message = 'If that email exists, a reset link has been sent.';

        if ($user && $user->isActive && ! $this->requiresEmailConfirmation($user)) {
            PasswordResetToken::query()
                ->where('userId', $user->id)
                ->whereNull('usedAt')
                ->update(['usedAt' => now()]);

            $token = Str::random(64);
            PasswordResetToken::query()->create([
                'userId' => $user->id,
                'token' => $token,
                'expiresAt' => now()->addHour(),
                'createdAt' => now(),
            ]);

            AuthMail::send($user->email, $user->name, 'Reset your SalesDock password', [
                'preheader' => 'This password link expires in one hour.',
                'heading' => 'Reset your password',
                'kicker' => "Let's get you back into the account.",
                'paragraphs' => [
                    'Hi <strong style="color:#111827;">'.e($user->name).'</strong>, we received a request to reset the password for this SalesDock account.',
                ],
                'url' => route('password.reset', ['token' => $token]),
                'label' => 'Reset password',
                'note' => 'This link expires in 1 hour. If you did not ask for a reset, ignore this email and the password will stay the same.',
            ]);
        }

        return back()->with('status', $message);
    }

    public function resetForm(Request $request)
    {
        return view('auth.reset', ['token' => $request->query('token')]);
    }

    public function reset(Request $request)
    {
        Turnstile::verify($request, 'reset-password');

        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $reset = PasswordResetToken::query()->where('token', $data['token'])->first();

        if (! $reset || $reset->usedAt || $reset->expiresAt->isPast()) {
            return back()->withErrors(['token' => 'Invalid or expired reset link']);
        }

        $user = User::query()->find($reset->userId);
        if (! $user || $this->requiresEmailConfirmation($user)) {
            return back()->withErrors(['token' => 'Invalid or expired reset link']);
        }

        $user->update([
            'passwordHash' => Hash::make($data['password'], ['rounds' => 12]),
        ]);
        $reset->forceFill(['usedAt' => now()])->save();

        return redirect()->route('login')->with('status', 'Password updated successfully');
    }

    private function requiresEmailConfirmation(User $user): bool
    {
        return TenantRegistration::query()->where('email', $user->email)
            ->whereNotNull('phoneVerifiedAt')->where('emailVerified', false)->exists();
    }

    public function sendRecoveryConfirmation(Request $request): RedirectResponse
    {
        $registration = $this->pendingRecoveryConfirmation($request->user());
        $token = Str::random(64);
        $registration->update(['verifyToken' => hash('sha256', $token), 'verifyTokenExp' => now()->addMinutes(30)]);
        $sent = AuthMail::send($registration->email, $registration->ownerName, 'Confirm your SalesDock recovery email', [
            'paragraphs' => ['Open the link while signed in to confirm this inbox for password recovery. The link expires in 30 minutes.'],
            'url' => route('recovery-email.confirm', ['token' => $token]),
            'label' => 'Confirm recovery email',
        ]);

        return back()->with('status', $sent ? 'Check your inbox and spam folder for the confirmation link.' : 'The email could not be sent. Try again shortly.');
    }

    public function confirmRecoveryEmail(Request $request): RedirectResponse
    {
        $registration = $this->pendingRecoveryConfirmation($request->user());
        $token = $request->query('token');
        abort_unless(is_string($token) && $registration->verifyTokenExp?->isFuture()
            && hash_equals((string) $registration->verifyToken, hash('sha256', $token)), 403);

        $registration->update(['emailVerified' => true, 'emailVerifiedAt' => now(), 'verifyToken' => null, 'verifyTokenExp' => null]);

        return redirect()->route('profile')->with('status', 'Your email is confirmed for password recovery.');
    }

    private function pendingRecoveryConfirmation(User $user): TenantRegistration
    {
        return TenantRegistration::query()->where('email', $user->email)
            ->whereNotNull('phoneVerifiedAt')->where('emailVerified', false)->firstOrFail();
    }
}
