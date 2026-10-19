<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\TenantRegistration;
use App\Services\PaymentManager;
use App\Services\TenantProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SelectPlanController extends Controller
{
    public function create(Request $request, PaymentManager $payments)
    {
        $registration = $this->registration($request->query('token'));

        return view('auth.select-plan', [
            'registration' => $registration,
            'plans' => $registration
                ? Plan::query()->purchasable()->orderBy('monthlyPrice')->get()
                : collect(),
            'token' => $request->query('token'),
            'gatewayLabel' => $payments->active()->label(),
        ]);
    }

    public function store(Request $request, TenantProvisioner $provisioner)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'planId' => ['required', 'string'],
            'billingCycle' => ['required', 'in:MONTHLY,QUARTERLY,ANNUALLY'],
        ]);

        $registration = $this->registration($data['token']);
        if (! $registration) {
            return back()->withErrors(['token' => 'Invalid or expired plan selection link.']);
        }

        $plan = Plan::query()->purchasable()->whereKey($data['planId'])->first();
        if (! $plan) {
            return back()->withErrors(['planId' => 'Plan not found']);
        }

        $tenant = $provisioner->provision($registration);
        $subscription = Subscription::query()->where('tenantId', $tenant->id)->first();

        if ($subscription?->status === 'ACTIVE') {
            $registration->forceFill([
                'planSelected' => true,
                'planToken' => null,
                'planTokenExp' => null,
            ])->save();

            return view('auth.message', [
                'title' => 'Already subscribed',
                'body' => 'This account is already subscribed. Sign in to manage billing.',
            ]);
        }

        $start = now();
        $end = $provisioner->periodEnd($start, $data['billingCycle']);
        $amount = $provisioner->price($plan, $data['billingCycle']);
        if ($amount <= 0) {
            return back()->withErrors(['billingCycle' => $plan->name.' is not available on that billing cycle. Choose another cycle.']);
        }
        $invoice = $provisioner->openInvoice($tenant->id);

        $payload = [
            'billingCycle' => $data['billingCycle'],
            'amount' => $amount,
            'gateway' => strtoupper((string) PlatformSetting::payment()['provider']),
            'gatewayRef' => $plan->id,
            'periodStart' => $start,
            'periodEnd' => $end,
            'dueDate' => now()->addMinutes(30),
        ];

        if ($invoice) {
            $invoice->update($payload);
        } else {
            $invoice = BillingInvoice::query()->create($payload + [
                'tenantId' => $tenant->id,
                'invoiceNumber' => 'INV-'.now()->timestamp.'-'.strtoupper(Str::random(5)),
                'currency' => 'NGN',
                'status' => 'PENDING',
            ]);
        }

        return redirect()->route('billing.checkout', ['invoice' => $invoice->invoiceNumber]);
    }

    private function registration(?string $token): ?TenantRegistration
    {
        if (! $token) {
            return null;
        }

        $registration = TenantRegistration::query()->where('planToken', $token)->first();
        if (! $registration || ! $registration->emailVerified) {
            return null;
        }
        if ($registration->planTokenExp && $registration->planTokenExp->isPast()) {
            return null;
        }

        return $registration;
    }
}
