<?php

namespace App\Http\Controllers\Billing;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Services\BillingService;
use App\Services\PaymentManager;
use App\Services\SaleRecorder;
use App\Support\PlanTour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function checkout(string $invoice, PaymentManager $payments)
    {
        $record = BillingInvoice::query()->where('invoiceNumber', $invoice)->where('status', 'PENDING')->firstOrFail();
        $gateway = $payments->active();

        return view('billing.pay', [
            'invoice' => $record,
            'ready' => $gateway->ready(),
            'gatewayLabel' => $gateway->label(),
        ]);
    }

    public function pay(BillingInvoice $invoice, PaymentManager $payments)
    {
        abort_unless($invoice->tenantId === $this->user()->tenantId, 404);
        abort_unless($invoice->status === 'PENDING', 404);
        $gateway = $payments->active();

        return view('billing.pay', [
            'invoice' => $invoice,
            'ready' => $gateway->ready(),
            'gatewayLabel' => $gateway->label(),
        ]);
    }

    public function start(string $invoice, PaymentManager $payments)
    {
        $record = BillingInvoice::query()->where('invoiceNumber', $invoice)->where('status', 'PENDING')->firstOrFail();
        $tenant = Tenant::query()->findOrFail($record->tenantId);
        $user = Auth::user();
        $gateway = $payments->active();

        try {
            $link = $gateway->checkoutLink([
                'reference' => $record->invoiceNumber,
                'amount' => (float) $record->amount,
                'email' => $user?->tenantId === $tenant->id ? $user->email : $tenant->email,
                'name' => $tenant->name,
                'redirect' => route('billing.verify', ['ref' => $record->invoiceNumber]),
                'title' => 'SalesDock',
                'description' => 'Subscription '.$record->invoiceNumber,
            ]);
        } catch (\Throwable $e) {
            Log::warning($gateway->label().' checkout link failed', ['invoice' => $record->invoiceNumber, 'error' => $e->getMessage()]);

            return back()->withErrors(['payment' => 'We could not open the payment page: '.$e->getMessage()]);
        }

        return redirect()->away($link);
    }

    public function verify(Request $request, PaymentManager $payments, BillingService $billing, SaleRecorder $sales)
    {
        $ref = (string) $request->query('ref', $request->query('reference', ''));
        abort_unless($ref !== '', 404);
        $back = Auth::check() ? 'billing' : 'login';
        $gateway = $payments->active();

        if (in_array($request->query('status'), ['cancelled', 'failed'], true)) {
            $cancelled = $request->query('status') === 'cancelled';

            return $this->failed($ref, $back, $cancelled ? 'cancelled' : 'failed', $cancelled
                ? 'Payment was cancelled. You can try the same invoice again.'
                : 'Payment did not go through. You can try the same invoice again.');
        }

        try {
            $payment = $this->resolvePayment($request, $gateway, $ref);
        } catch (\Throwable $e) {
            return $this->failed($ref, $back, 'failed', 'We could not confirm the payment yet: '.$e->getMessage().' If you were charged, it will update automatically within a few minutes.');
        }

        if ($gateway->baseReference((string) ($payment['tx_ref'] ?? '')) !== $ref || $billing->settlePayment($payment, $gateway, $sales) === null) {
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

    public function webhook(Request $request, PaymentManager $payments, BillingService $billing, SaleRecorder $sales)
    {
        return $this->handleWebhook($request, $payments->driver('flutterwave'), $billing, $sales);
    }

    public function webhookPaystack(Request $request, PaymentManager $payments, BillingService $billing, SaleRecorder $sales)
    {
        return $this->handleWebhook($request, $payments->driver('paystack'), $billing, $sales);
    }

    private function handleWebhook(Request $request, PaymentGateway $gateway, BillingService $billing, SaleRecorder $sales)
    {
        if (! $gateway->webhookTrusted($request)) {
            return response('invalid signature', 401);
        }

        $payload = $request->json()->all();
        $eventType = (string) ($payload['event'] ?? $payload['event.type'] ?? '');
        $supportedEvents = $gateway->name() === 'paystack' ? ['charge.success'] : ['charge.completed'];
        if (! in_array($eventType, $supportedEvents, true)) {
            return response('ignored');
        }
        [$transactionId, $reference] = $this->webhookIds($gateway, $payload);
        if ($transactionId === '' && $reference === '') {
            return response('missing reference', 422);
        }

        $event = WebhookEvent::query()->firstOrCreate(
            ['reference' => $gateway->name().':'.$eventType.':'.($transactionId !== '' ? $transactionId : $reference)],
            [
                'gateway' => $gateway->name(),
                'eventType' => (string) ($payload['event'] ?? $payload['event.type'] ?? 'charge.completed'),
                'payload' => $payload,
                'createdAt' => now(),
            ]
        );

        if ($event->processed) {
            return response('ok');
        }

        try {
            $payment = $transactionId !== ''
                ? $gateway->verifyTransaction($transactionId)
                : $gateway->verifyByReference($reference);
            if ($gateway->successful($payment['status'] ?? null)) {
                $result = $billing->settlePayment($payment, $gateway, $sales);
                if ($result === null) {
                    $event->forceFill(['error' => 'No matching payable invoice or order.'])->save();

                    return response('unmatched', 422);
                }
            }
            $event->forceFill(['processed' => true, 'processedAt' => now(), 'error' => null])->save();
        } catch (\Throwable $e) {
            $event->forceFill(['error' => $e->getMessage()])->save();

            return response('verify failed', 502);
        }

        return response('ok');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function webhookIds(PaymentGateway $gateway, array $payload): array
    {
        if ($gateway->name() === 'paystack') {
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            return [
                (string) ($data['id'] ?? ''),
                (string) ($data['reference'] ?? ''),
            ];
        }

        return [
            (string) ($payload['data']['id'] ?? $payload['id'] ?? ''),
            (string) ($payload['data']['tx_ref'] ?? $payload['txRef'] ?? ''),
        ];
    }

    private function resolvePayment(Request $request, PaymentGateway $gateway, string $ref): array
    {
        $transactionId = (string) $request->query('transaction_id', '');
        if ($transactionId !== '') {
            return $gateway->verifyTransaction($transactionId);
        }

        $txRef = (string) $request->query('tx_ref', $request->query('reference', $request->query('trxref', $ref)));

        return $gateway->verifyByReference($txRef !== '' ? $txRef : $ref);
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
