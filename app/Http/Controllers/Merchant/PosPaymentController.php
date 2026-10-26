<?php

namespace App\Http\Controllers\Merchant;

use App\Models\Order;
use App\Models\Transaction;
use App\Services\PosPayments;
use App\Support\Permissions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PosPaymentController extends MerchantController
{
    public function show(Order $order): View
    {
        $this->guard($order);

        return view('merchant.pos-payment', [
            'order' => $order,
            'transaction' => Transaction::query()->where('orderId', $order->id)->firstOrFail(),
        ]);
    }

    public function start(Order $order, PosPayments $payments): RedirectResponse
    {
        $this->guard($order);
        try {
            return redirect()->away($payments->start($order));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['payment' => 'Could not open checkout. Check payment status before retrying; this sale remains unpaid until confirmed.']);
        }
    }

    public function refresh(Order $order, PosPayments $payments): RedirectResponse
    {
        $this->guard($order);
        try {
            $payments->reconcile($order);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('pos.payments.show', $order)->withErrors(['payment' => 'Payment could not be confirmed yet. If charged, wait for confirmation before paying again.']);
        }

        return redirect()->route('pos.payments.show', $order);
    }

    public function status(Order $order): JsonResponse
    {
        $this->guard($order);
        $transaction = Transaction::query()->where('orderId', $order->id)->firstOrFail();

        return response()->json([
            'status' => $order->status,
            'paymentStatus' => $transaction->status,
            'needsReview' => (bool) ($transaction->metadata['needsReview'] ?? false),
        ])->header('Cache-Control', 'no-store');
    }

    private function guard(Order $order): void
    {
        abort_unless($order->tenantId === $this->tenantId() && $order->channel === 'POS' && $order->paymentVerificationRequired, 404);
        $user = $this->user()->loadMissing('role');
        $permissions = Permissions::normalize($user->role?->permissions ?? []);
        if (($user->role?->role ?? 'CASHIER') === 'CASHIER') {
            abort_unless($order->cashierId === $user->id, 404);
        }
        $manager = $user->role?->name === 'Owner' || ! empty($permissions['canManageOrders']);
        abort_unless($manager || ($order->cashierId === $user->id && ! empty($permissions['canUsePOS'])), 403);
    }
}
