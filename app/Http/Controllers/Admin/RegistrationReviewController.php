<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantRegistration;
use App\Support\AuthMail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RegistrationReviewController extends Controller
{
    public function index(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true)) {
            $status = '';
        }
        $query = TenantRegistration::query()->latest('createdAt');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($row) use ($like) {
                $row->where('businessName', 'ilike', $like)
                    ->orWhere('ownerName', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like);
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        $perPage = 20;
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('admin.registrations', [
            'registrations' => $query->forPage($page, $perPage)->get(),
            'q' => $q,
            'status' => $status,
            'filtered' => $q !== '' || $status !== '',
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function show(TenantRegistration $registration)
    {
        return view('admin.registration', ['registration' => $registration]);
    }

    public function approve(TenantRegistration $registration)
    {
        if ($registration->status !== 'PENDING') {
            return back()->withErrors(['registration' => 'This application is not pending.']);
        }
        if (! $registration->emailVerified) {
            return back()->withErrors(['registration' => 'Applicant has not verified their email yet']);
        }

        $token = Str::random(64);
        $registration->forceFill([
            'status' => 'APPROVED',
            'reviewedAt' => Carbon::now(),
            'reviewedBy' => $this->user()->id,
            'planToken' => $token,
            'planTokenExp' => Carbon::now()->addHours(72),
        ])->save();

        $this->sendPlanLink($registration, $token);

        return back()->with('status', 'Approved and email sent.');
    }

    public function resend(TenantRegistration $registration)
    {
        if ($registration->status !== 'APPROVED') {
            return back()->withErrors(['registration' => 'This application is not approved.']);
        }
        if ($registration->planSelected) {
            return back()->withErrors(['registration' => 'This shop has already chosen a plan.']);
        }

        $token = Str::random(64);
        $registration->forceFill([
            'planToken' => $token,
            'planTokenExp' => Carbon::now()->addHours(72),
        ])->save();

        $this->sendPlanLink($registration, $token);

        return back()->with('status', 'Plan link sent.');
    }

    public function reject(Request $request, TenantRegistration $registration)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        if ($registration->status !== 'PENDING') {
            return back()->withErrors(['registration' => 'This application is not pending.']);
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        $registration->forceFill([
            'status' => 'REJECTED',
            'reviewedAt' => Carbon::now(),
            'reviewedBy' => $this->user()->id,
            'rejectionReason' => $reason !== '' ? $reason : null,
        ])->save();
        AuthMail::send($registration->email, $registration->ownerName, 'Update on your SalesDock application', [
            'preheader' => 'The application was not approved.',
            'heading' => 'Application not approved',
            'kicker' => 'Here is the update from the review.',
            'paragraphs' => [
                'Hi <strong style="color:#111827;">'.e($registration->ownerName).'</strong>, after reviewing <strong style="color:#111827;">'.e($registration->businessName).'</strong>, we are not able to approve it at this time.',
            ],
            'summaryTitle' => 'Reason',
            'summary' => $reason !== '' ? ['Review' => $reason] : [],
            'note' => 'You can apply again with the same email after this rejection. Reply to this email if you think the review missed something.',
        ]);

        return back()->with('status', 'Rejected and email sent.');
    }

    private function sendPlanLink(TenantRegistration $registration, string $token): void
    {
        AuthMail::send($registration->email, $registration->ownerName, 'Your SalesDock application was approved', [
            'preheader' => 'Choose a plan within 72 hours to open the dashboard.',
            'heading' => "You're approved",
            'kicker' => 'SalesDock is ready for the business.',
            'paragraphs' => [
                'Hi <strong style="color:#111827;">'.e($registration->ownerName).'</strong>, the application for <strong style="color:#111827;">'.e($registration->businessName).'</strong> has been approved.',
                'The next step is choosing a plan. A free plan opens the dashboard immediately. A paid plan stays pending until Flutterwave confirms the payment.',
            ],
            'url' => route('select-plan', ['token' => $token]),
            'label' => 'Choose your plan',
            'note' => 'This link expires in 72 hours. You can return to it if a payment window is closed.',
        ]);
    }
}
