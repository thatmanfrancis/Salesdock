<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\WebhookEvent;
use App\Services\BillingService;
use App\Services\Flutterwave;
use App\Services\SaleRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function checkout(string $invoice, Flutterwave $flutterwave)
    {
        $record = BillingInvoice::query()->where('invoiceNumber', $invoice)->where('status', 'PENDING')->firstOrFail();
        $tenant = \App\Models\Tenant::query()->findOrFail($record->tenantId);

        return view('billing.pay', [
            'invoice' => $record,
            'publicKey' => $flutterwave->publicKey(),
            'email' => $tenant->email,
            'name' => $tenant->name,
            'redirect' => route('billing.verify', ['ref' => $record->invoiceNumber]),
        ]);
    }

    public function pay(BillingInvoice $invoice, Flutterwave $flutterwave)
    {
        abort_unless($invoice->tenantId === $this->user()->tenantId, 404);
        abort_unless($invoice->status === 'PENDING', 404);

        return view('billing.pay', [
            'invoice' => $invoice,
            'publicKey' => $flutterwave->publicKey(),
            'email' => $this->user()->email,
            'name' => $this->user()->name,
            'redirect' => route('billing.verify', ['ref' => $invoice->invoiceNumber]),
        ]);
    }

    public function verify(Request $request, Flutterwave $flutterwave, BillingService $billing, SaleRecorder $sales)
    {
        $ref = (string) $request->query('ref');
        abort_unless($ref !== '', 404);

        try {
            $payment = $flutterwave->verify($ref);
        } catch (\Throwable $e) {
            return redirect()->route('billing')->withErrors(['payment' => $e->getMessage()]);
        }

        if (! $flutterwave->successful($payment['status'] ?? null)) {
            return redirect()->route('billing')->withErrors(['payment' => 'Payment did not complete. You can try the same invoice again.']);
        }

        $billing->settleReference($ref, $payment, $sales);

        return redirect()->route(Auth::check() ? 'billing' : 'login')->with('status', 'Payment received.');
    }

    public function webhook(Request $request, Flutterwave $flutterwave, BillingService $billing, SaleRecorder $sales)
    {
        if (! $flutterwave->webhookTrusted($request->header('verif-hash'))) {
            return response('invalid signature', 401);
        }

        $payload = $request->json()->all();
        $reference = (string) ($payload['data']['tx_ref'] ?? $payload['txRef'] ?? '');
        if ($reference === '') {
            return response('missing reference', 422);
        }

        $event = WebhookEvent::query()->firstOrCreate(
            ['reference' => (string) ($payload['data']['id'] ?? $reference)],
            [
                'gateway' => 'flutterwave',
                'eventType' => (string) ($payload['event'] ?? 'charge.completed'),
                'payload' => $payload,
                'createdAt' => now(),
            ]
        );

        if ($event->processed) {
            return response('ok');
        }

        try {
            $payment = $flutterwave->verify($reference);
            if ($flutterwave->successful($payment['status'] ?? null)) {
                $billing->settleReference($reference, $payment, $sales);
            }
            $event->forceFill(['processed' => true, 'processedAt' => now()])->save();
        } catch (\Throwable $e) {
            $event->forceFill(['error' => $e->getMessage()])->save();

            return response('verify failed', 502);
        }

        return response('ok');
    }
}
