<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesLedger;
use App\Models\SalesLedgerItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\VatSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleRecorder
{
    public function sell(string $tenantId, array $lines, array $meta): Order
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => 'Add at least one product.']);
        }

        return DB::transaction(function () use ($tenantId, $lines, $meta) {
            if (! empty($meta['resumeHoldId'])) {
                $held = \App\Models\Hold::query()->where('tenantId', $tenantId)->whereKey($meta['resumeHoldId'])->first();
                if ($held?->status === 'ACTIVE') {
                    $selling = (bool) ($meta['complete'] ?? true);
                    $this->releaseHold($held, 'RESTORED', $selling ? 'Brought back to the cart and sold' : 'Replaced by a new hold');
                }
            }

            $vat = VatSettings::query()->where('tenantId', $tenantId)->first();
            $rateDefault = (float) ($vat->globalVatRate ?? 7.5);
            $inclusive = (bool) ($vat->isInclusive ?? false);
            $discount = max(0, (float) ($meta['discount'] ?? 0));
            $complete = (bool) ($meta['complete'] ?? true);

            $gross = 0;
            $tax = 0;
            $cogs = 0;
            $prepared = [];

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = Product::query()->where('tenantId', $tenantId)->lockForUpdate()->findOrFail($line['productId']);
                $qty = (int) $line['qty'];
                if ($qty < 1) {
                    continue;
                }
                $free = (int) $product->currentStock - (int) $product->reservedQty;
                if ($free < $qty) {
                    throw ValidationException::withMessages(['cart' => "{$product->name} only has {$free} available."]);
                }
                if (! $complete) {
                    $product->forceFill(['reservedQty' => (int) $product->reservedQty + $qty])->save();
                }

                $unit = (float) ($line['unitPrice'] ?? $product->price);
                $lineGross = $unit * $qty;
                $rate = (float) ($product->vatRateOverride ?? $rateDefault) / 100;
                if ($inclusive) {
                    $net = $rate > 0 ? $lineGross / (1 + $rate) : $lineGross;
                    $vatAmount = $lineGross - $net;
                } else {
                    $net = $lineGross;
                    $vatAmount = $lineGross * $rate;
                }

                $gross += $lineGross;
                $tax += $vatAmount;
                $cogs += (float) $product->costPrice * $qty;
                $prepared[] = compact('product', 'qty', 'unit', 'lineGross', 'vatAmount', 'rate');
            }

            if ($prepared === []) {
                throw ValidationException::withMessages(['cart' => 'Add at least one product.']);
            }

            $discount = min($discount, $gross);
            $netAmount = max(0, $gross - $discount + ($inclusive ? 0 : $tax));
            $customer = $this->customer($tenantId, $meta, $complete ? $netAmount : 0);

            $order = Order::query()->create([
                'tenantId' => $tenantId,
                'branchId' => $meta['branchId'] ?? null,
                'cashierId' => $meta['cashierId'] ?? null,
                'status' => $complete ? 'COMPLETED' : 'PENDING',
                'channel' => $meta['channel'] ?? 'POS',
                'totalAmount' => $gross,
                'discountAmount' => $discount,
                'taxAmount' => $tax,
                'netAmount' => $netAmount,
                'customerId' => $customer?->id,
                'customerName' => $meta['customerName'] ?? $customer?->name,
                'customerEmail' => $meta['customerEmail'] ?? $customer?->email,
                'customerPhone' => $meta['customerPhone'] ?? $customer?->phone,
                'customerAddress' => $meta['customerAddress'] ?? $customer?->address,
                'paymentRef' => $meta['paymentRef'] ?? ($complete ? null : 'SD-'.strtoupper(Str::random(10))),
                'notes' => $meta['notes'] ?? null,
            ]);

            foreach ($prepared as $row) {
                OrderItem::query()->create([
                    'orderId' => $order->id,
                    'productId' => $row['product']->id,
                    'quantity' => $row['qty'],
                    'unitPrice' => $row['unit'],
                    'originalPrice' => $row['product']->price,
                    'discountAmount' => 0,
                    'lineTotal' => $row['lineGross'],
                    'isPriceOverridden' => abs($row['unit'] - (float) $row['product']->price) > 0.009,
                    'createdAt' => now(),
                ]);

                if ($complete) {
                    $before = (int) $row['product']->currentStock;
                    $after = $before - $row['qty'];
                    $row['product']->forceFill(['currentStock' => $after])->save();
                    StockMovement::query()->create([
                        'productId' => $row['product']->id,
                        'tenantId' => $tenantId,
                        'type' => 'SALE',
                        'quantity' => -$row['qty'],
                        'beforeQty' => $before,
                        'afterQty' => $after,
                        'reference' => $order->id,
                        'createdAt' => now(),
                    ]);
                }
            }

            $method = $meta['method'] ?? 'CASH';
            $transaction = Transaction::query()->create([
                'orderId' => $order->id,
                'tenantId' => $tenantId,
                'gateway' => $method === 'CARD' ? 'FLUTTERWAVE' : ($method === 'TRANSFER' ? 'TRANSFER' : 'CASH'),
                'method' => $method,
                'amount' => $netAmount,
                'status' => $complete ? 'SUCCESS' : 'PENDING',
                'reference' => $order->paymentRef,
                'completedAt' => $complete ? now() : null,
            ]);

            if ($complete) {
                $this->ledger($order, $transaction, $prepared, $cogs, $meta);
            }

            return $order;
        });
    }

    public function releaseHold(\App\Models\Hold $hold, string $status, string $reason): void
    {
        if ($hold->status !== 'ACTIVE') {
            return;
        }

        $run = function () use ($hold, $status, $reason) {
            $items = OrderItem::query()->where('orderId', $hold->orderId)->get();
            foreach ($items as $item) {
                $product = Product::query()->where('tenantId', $hold->tenantId)->lockForUpdate()->find($item->productId);
                if ($product) {
                    $product->forceFill([
                        'reservedQty' => max(0, (int) $product->reservedQty - (int) $item->quantity),
                    ])->save();
                }
            }

            $hold->forceFill([
                'status' => $status,
                'restoredAt' => $status === 'RESTORED' ? now() : $hold->restoredAt,
                'expiredAt' => $status === 'CANCELLED' ? now() : $hold->expiredAt,
            ])->save();

            Order::query()->whereKey($hold->orderId)->update([
                'status' => 'CANCELLED',
                'cancelReason' => $reason,
                'cancelledAt' => now(),
            ]);

            Transaction::query()->where('orderId', $hold->orderId)->where('status', 'PENDING')->update([
                'status' => 'FAILED',
            ]);
        };

        DB::transactionLevel() > 0 ? $run() : DB::transaction($run);
    }

    public function completePending(Order $order, string $gatewayRef): Order
    {
        if ($order->status === 'COMPLETED') {
            return $order;
        }

        return DB::transaction(function () use ($order, $gatewayRef) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'COMPLETED') {
                return $order;
            }

            $items = OrderItem::query()->where('orderId', $order->id)->get();
            $prepared = [];
            $cogs = 0;

            foreach ($items as $item) {
                $product = Product::query()->where('tenantId', $order->tenantId)->lockForUpdate()->findOrFail($item->productId);
                $before = (int) $product->currentStock;
                $after = $before - (int) $item->quantity;
                $product->forceFill(['currentStock' => $after])->save();
                StockMovement::query()->create([
                    'productId' => $product->id,
                    'tenantId' => $order->tenantId,
                    'type' => 'SALE',
                    'quantity' => -((int) $item->quantity),
                    'beforeQty' => $before,
                    'afterQty' => $after,
                    'reference' => $order->id,
                    'createdAt' => now(),
                ]);
                $cogs += (float) $product->costPrice * (int) $item->quantity;
                $prepared[] = [
                    'product' => $product,
                    'qty' => (int) $item->quantity,
                    'unit' => (float) $item->unitPrice,
                    'lineGross' => (float) $item->lineTotal,
                    'vatAmount' => 0,
                    'rate' => 0,
                ];
            }

            $order->forceFill(['status' => 'COMPLETED'])->save();
            $transaction = Transaction::query()->where('orderId', $order->id)->first();
            if ($transaction) {
                $transaction->forceFill([
                    'status' => 'SUCCESS',
                    'gatewayRef' => $gatewayRef,
                    'completedAt' => now(),
                ])->save();
            }

            $this->ledger($order, $transaction, $prepared, $cogs, [
                'channel' => $order->channel,
                'cashierId' => $order->cashierId,
                'method' => $transaction->method ?? 'CARD',
            ]);

            if ($order->customerId) {
                $customer = Customer::query()->find($order->customerId);
                if ($customer) {
                    $customer->forceFill([
                        'totalOrders' => $customer->totalOrders + 1,
                        'totalSpend' => (float) $customer->totalSpend + (float) $order->netAmount,
                        'loyaltyPoints' => $customer->loyaltyPoints + (int) floor((float) $order->netAmount / 100),
                    ])->save();
                }
            }

            return $order->refresh();
        });
    }

    private function customer(string $tenantId, array $meta, float $spend): ?Customer
    {
        $phone = $meta['customerPhone'] ?? null;
        $email = $meta['customerEmail'] ?? null;
        $name = $meta['customerName'] ?? null;
        if (! $phone && ! $email && ! $name) {
            return null;
        }

        $customer = null;
        if ($phone) {
            $customer = Customer::query()->where('tenantId', $tenantId)->where('phone', $phone)->first();
        }
        if (! $customer && $email) {
            $customer = Customer::query()->where('tenantId', $tenantId)->where('email', $email)->first();
        }

        if (! $customer) {
            $customer = Customer::query()->create([
                'tenantId' => $tenantId,
                'name' => $name ?: 'Walk-in customer',
                'email' => $email,
                'phone' => $phone,
                'address' => $meta['customerAddress'] ?? null,
                'firstChannel' => $meta['channel'] ?? 'POS',
                'totalOrders' => $spend > 0 ? 1 : 0,
                'totalSpend' => $spend,
                'loyaltyPoints' => (int) floor($spend / 100),
            ]);

            return $customer;
        }

        if ($spend > 0) {
            $customer->forceFill([
                'totalOrders' => $customer->totalOrders + 1,
                'totalSpend' => (float) $customer->totalSpend + $spend,
                'loyaltyPoints' => $customer->loyaltyPoints + (int) floor($spend / 100),
            ])->save();
        }

        return $customer;
    }

    private function ledger(Order $order, ?Transaction $transaction, array $prepared, float $cogs, array $meta): void
    {
        $ledger = SalesLedger::query()->create([
            'tenantId' => $order->tenantId,
            'orderId' => $order->id,
            'transactionId' => $transaction?->id,
            'channel' => $meta['channel'] ?? $order->channel,
            'cashierId' => $meta['cashierId'] ?? $order->cashierId,
            'customerName' => $order->customerName,
            'paymentMethod' => $meta['method'] ?? 'CASH',
            'orderStatus' => 'COMPLETED',
            'grossRevenue' => $order->totalAmount,
            'netRevenue' => $order->netAmount,
            'totalVat' => $order->taxAmount,
            'totalCogs' => $cogs,
            'grossProfit' => (float) $order->netAmount - $cogs,
            'createdAt' => now(),
        ]);

        foreach ($prepared as $row) {
            $cost = (float) $row['product']->costPrice * $row['qty'];
            SalesLedgerItem::query()->create([
                'ledgerId' => $ledger->id,
                'productId' => $row['product']->id,
                'sku' => $row['product']->sku,
                'productName' => $row['product']->name,
                'quantity' => $row['qty'],
                'costPrice' => $row['product']->costPrice,
                'sellingPrice' => $row['unit'],
                'lineTotal' => $row['lineGross'],
                'vatPercentage' => $row['rate'] * 100,
                'vatAmount' => $row['vatAmount'],
                'grossLineTotal' => $row['lineGross'] + ($row['vatAmount'] ?? 0),
                'netLineTotal' => $row['lineGross'],
                'grossProfit' => $row['lineGross'] - $cost,
            ]);
        }

        AuditLog::query()->create([
            'tenantId' => $order->tenantId,
            'userId' => $meta['cashierId'] ?? $order->cashierId,
            'action' => 'POS_CHECKOUT_SUCCESS',
            'details' => ['orderId' => $order->id, 'netAmount' => (float) $order->netAmount],
            'timestamp' => now(),
        ]);

        ActivityLog::query()->create([
            'tenantId' => $order->tenantId,
            'userId' => $meta['cashierId'] ?? $order->cashierId,
            'actionType' => 'CHECKOUT',
            'module' => 'POS',
            'description' => 'Completed sale '.$order->id,
            'affectedEntityId' => $order->id,
            'timestamp' => now(),
        ]);

        Notification::query()->create([
            'tenantId' => $order->tenantId,
            'type' => 'NEW_ORDER',
            'title' => 'Sale completed',
            'message' => '₦'.number_format((float) $order->netAmount, 2).' collected.',
            'entityId' => $order->id,
            'createdAt' => now(),
        ]);
    }
}
