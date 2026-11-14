<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Subscription;
use App\Services\RecurringBilling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutoRenewController extends Controller
{
    public function show(Request $request, BillingInvoice $invoice, RecurringBilling $billing): View
    {
        abort_unless($billing->canAuthorize($request->user(), $invoice->tenantId), 403);
        $history = $invoice->billingMetadata['renewalAuthorizations'] ?? [];
        $consent = $history ? array_pop($history) : ($invoice->billingMetadata['consent'] ?? null);
        abort_unless($invoice->status === 'PAID' && $consent, 404);
        if (! empty($invoice->billingMetadata['consent']) && ! empty($invoice->billingMetadata['renewalAuthorizations'])) {
            array_unshift($history, $invoice->billingMetadata['consent']);
        }

        return view('billing.authorization', ['invoice' => $invoice, 'consent' => $consent, 'previousConsents' => $history,
            'subscription' => Subscription::query()->where('tenantId', $invoice->tenantId)->first()]);
    }

    public function store(Request $request, RecurringBilling $billing): RedirectResponse
    {
        $tenantId = (string) $request->user()->tenantId;
        abort_unless($billing->canAuthorize($request->user(), $tenantId), 403);
        $subscription = Subscription::query()->where('tenantId', $tenantId)->firstOrFail();
        $billing->enable($subscription, $request);

        return redirect()->route('billing')->with('status', 'Automatic renewal is on. No payment was taken today.');
    }

    public function destroy(Request $request, RecurringBilling $billing): RedirectResponse
    {
        $tenantId = (string) $request->user()->tenantId;
        abort_unless($billing->canAuthorize($request->user(), $tenantId), 403);
        $subscription = Subscription::query()->where('tenantId', $tenantId)->firstOrFail();
        $billing->cancel($subscription);

        return redirect()->route('billing')->with('status', 'Automatic renewal is off. Your current paid period remains available.');
    }
}
