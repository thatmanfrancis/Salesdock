<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TenantRegistration;
use App\Support\AuthMail;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! $request->query('token')) {
            return view('auth.verify', ['status' => 'error', 'message' => 'No token provided.']);
        }

        $registration = TenantRegistration::query()->where('verifyToken', $request->query('token'))->first();

        if (! $registration) {
            return view('auth.verify', ['status' => 'error', 'message' => 'This verification link is not valid.']);
        }

        if (! $registration->emailVerified) {
            if ($registration->verifyTokenExp && $registration->verifyTokenExp->isPast()) {
                return view('auth.verify', ['status' => 'error', 'message' => 'This verification link has expired. Register again to get a new one.']);
            }

            $registration->forceFill([
                'emailVerified' => true,
                'emailVerifiedAt' => now(),
            ])->save();

            AuthMail::send($registration->email, $registration->ownerName, "We've received your application — SalesDock", [
                'preheader' => 'Your email is verified. The application is now in review.',
                'heading' => 'Registration received',
                'kicker' => 'Your email is verified. Here is what we have on file.',
                'paragraphs' => [
                    'Hi <strong style="color:#111827;">'.e($registration->ownerName).'</strong>, thank you for applying. We have received the application for <strong style="color:#111827;">'.e($registration->businessName).'</strong> and the review has started.',
                ],
                'summaryTitle' => 'Application summary',
                'summary' => [
                    'Owner' => $registration->ownerName,
                    'Business' => $registration->businessName,
                    'Email' => $registration->email,
                    'Status' => 'Waiting for review',
                ],
                'steps' => [
                    'Our team reviews the application, usually within 24–48 hours.',
                    'You will get an email when it is approved, or if something needs to be corrected.',
                    'After approval, you choose a plan and can sign in.',
                ],
                'note' => 'Reply to this email if any of the details above are wrong.',
            ]);
        }

        return view('auth.verify', [
            'status' => 'success',
            'message' => 'Email verified!',
        ]);
    }
}
