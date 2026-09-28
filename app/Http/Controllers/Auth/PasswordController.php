<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Support\AuthMail;
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
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower(trim($data['email']));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        $message = 'If that email exists, a reset link has been sent.';

        if ($user && $user->isActive) {
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
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $reset = PasswordResetToken::query()->where('token', $data['token'])->first();

        if (! $reset || $reset->usedAt || $reset->expiresAt->isPast()) {
            return back()->withErrors(['token' => 'Invalid or expired reset link']);
        }

        User::query()->whereKey($reset->userId)->update([
            'passwordHash' => Hash::make($data['password'], ['rounds' => 12]),
        ]);
        $reset->forceFill(['usedAt' => now()])->save();

        return redirect()->route('login')->with('status', 'Password updated successfully');
    }
}
