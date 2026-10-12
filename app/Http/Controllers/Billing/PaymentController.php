<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Services\BillingService;
use App\Services\Flutterwave;
use App\Services\SaleRecorder;
use App\Support\PlanTour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function checkout(string $invoice, Flutterwave $flutterwave)
    {
        $record = BillingInvoice::query()->where('invoiceNumber', $invoice)->where('status', 'PENDING')->firstOrFail();

        return view('billing.pay', [
            'invoice' => $record,
            'ready' => $flutterwave->ready(),
        ]);
    }

    public function pay(BillingInvoice $invoice, Flutterwave $flutterwave)
    {
        abort_unless($invoice->tenantId === $this->user()->tenantId, 404);
        abort_unless($invoice->status === 'PENDING', 404);

        return view('billing.pay', [
            'invoice' => $invoice,
            'ready' => $flutterwave->ready(),
        ]);
    }

    public function start(string $invoice, Flutterwave $flutterwave)
    {
        $record = BillingInvoice::query()->where('invoiceNumber', $invoice)->where('status', 'PENDING')->firstOrFail();
        $tenant = Tenant::query()->findOrFail($record->tenantId);
        $user = Auth::user();

        try {
            $link = $flutterwave->checkoutLink([
                'reference' => $record->invoiceNumber,
                'amount' => (float) $record->amount,
                'email' => $user?->tenantId === $tenant->id ? $user->email : $tenant->email,
                'name' => $tenant->name,
                'redirect' => route('billing.verify', ['ref' => $record->invoiceNumber]),
                'title' => 'SalesDock',
                'description' => 'Subscription '.$record->invoiceNumber,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Flutterwave checkout link failed', ['invoice' => $record->invoiceNumber, 'error' => $e->getMessage()]);

            return back()->withErrors(['payment' => 'We could not open the payment page: '.$e->getMessage()]);
        }

        return redirect()->away($link);
    }

    public function verify(Request $request, Flutterwave $flutterwave, BillingService $billing, SaleRecorder $sales)
    {
        $ref = (string) $request->query('ref');
        abort_unless($ref !== '', 404);
        $back = Auth::check() ? 'billing' : 'login';

        if (in_array($request->query('status'), ['cancelled', 'failed'], true)) {
            $cancelled = $request->query('status') === 'cancelled';

            return $this->failed($ref, $back, $cancelled ? 'cancelled' : 'failed', $cancelled
                ? 'Payment was cancelled. You can try the same invoice again.'
                : 'Payment did not go through. You can try the same invoice again.');
        }

        try {
            $transactionId = (string) $request->query('transaction_id', '');
            $payment = $transactionId !== ''
                ? $flutterwave->verifyTransaction($transactionId)
                : $flutterwave->verifyByReference((string) $request->query('tx_ref', $ref));
        } catch (\Throwable $e) {
            return $this->failed($ref, $back, 'failed', 'We could not confirm the payment yet: '.$e->getMessage().' If you were charged, it will update automatically within a few minutes.');
        }

        if (Flutterwave::baseReference((string) ($payment['tx_ref'] ?? '')) !== $ref || $billing->settlePayment($payment, $flutterwave, $sales) === null) {
            return $this->failed($ref, $back, 'failed', 'Payment did not complete. You can try the same invoice again.');
        }

        $invoice = BillingInvoice::query()->where('invoiceNumber', $ref)->first();
        session()->put(PlanTour::SESSION_KEY, ['state' => 'success', 'invoice' => $invoice?->id]);

        return redirect()->route($back)->with('status', 'Payment received. Your subscription is active.');
    }

    /**
     * Signed-in owners get the result modal; guests (paying straight after sign-up) get the error on the pay page.
     */
    private function failed(string $ref, string $back, string $state, string $message)
    {
        if (! Auth::check()) {
            return $this->returnTo($ref, $back)->withErrors(['payment' => $message]);
        }

        $invoice = BillingInvoice::query()->where('invoiceNumber', $ref)->first();
        session()->put(PlanTour::SESSION_KEY, ['state' => $state, 'invoice' => $invoice?->id, 'message' => $message]);

        return $this->returnTo($ref, $back);
    }

    public function webhook(Request $request, Flutterwave $flutterwave, BillingService $billing, SaleRecorder $sales)
    {
        if (! $flutterwave->webhookTrusted($request->header('verif-hash'))) {
            return response('invalid signature', 401);
        }

        $payload = $request->json()->all();
        $transactionId = (string) ($payload['data']['id'] ?? $payload['id'] ?? '');
        $reference = (string) ($payload['data']['tx_ref'] ?? $payload['txRef'] ?? '');
        if ($transactionId === '' && $reference === '') {
            return response('missing reference', 422);
        }

        $event = WebhookEvent::query()->firstOrCreate(
            ['reference' => $transactionId !== '' ? $transactionId : $reference],
            [
                'gateway' => 'flutterwave',
                'eventType' => (string) ($payload['event'] ?? $payload['event.type'] ?? 'charge.completed'),
                'payload' => $payload,
                'createdAt' => now(),
            ]
        );

        if ($event->processed) {
            return response('ok');
        }

        try {
            $payment = ctype_digit($transactionId)
                ? $flutterwave->verifyTransaction($transactionId)
                : $flutterwave->verifyByReference($reference);
            if ($flutterwave->successful($payment['status'] ?? null)) {
                $billing->settlePayment($payment, $flutterwave, $sales);
            }
            $event->forceFill(['processed' => true, 'processedAt' => now()])->save();
        } catch (\Throwable $e) {
            $event->forceFill(['error' => $e->getMessage()])->save();

            return response('verify failed', 502);
        }

        return response('ok');
    }

    /**
     * Sends a failed or cancelled payment back to the invoice so it can be retried.
     */
    private function returnTo(string $invoiceNumber, string $fallback)
    {
        $invoice = BillingInvoice::query()->where('invoiceNumber', $invoiceNumber)->where('status', 'PENDING')->first();
        if (! $invoice) {
            return redirect()->route($fallback);
        }

        return Auth::check() && Auth::user()->tenantId === $invoice->tenantId
            ? redirect()->route('billing.pay', $invoice)
            : redirect()->route('billing.checkout', ['invoice' => $invoice->invoiceNumber]);
    }
}
