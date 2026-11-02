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
        abort_unless($invoice->status === 'PAID' && ! empty($invoice->billingMetadata['consent']), 404);

        return view('billing.authorization', ['invoice' => $invoice, 'consent' => $invoice->billingMetadata['consent'],
            'subscription' => Subscription::query()->where('tenantId', $invoice->tenantId)->first()]);
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
