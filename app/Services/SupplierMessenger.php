<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Support\AuthMail;

class SupplierMessenger
{
    /**
     * Channels a supplier can be reached on, in the order they are tried.
     *
     * @return list<string>
     */
    public static function channels(?Supplier $supplier): array
    {
        $channels = [];
        if ($supplier && filter_var((string) $supplier->contactEmail, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'email';
        }

        return $channels;
    }

    /**
     * Sends the purchase order to the supplier and returns the channel used, or null when nothing went out.
     */
    public function sendPurchaseOrder(PurchaseOrder $order, Supplier $supplier, Tenant $tenant): ?string
    {
        foreach (self::channels($supplier) as $channel) {
            $sent = match ($channel) {
                'email' => $this->email($order, $supplier, $tenant),
                default => false,
            };
            if ($sent) {
                return $channel;
            }
        }

        return null;
    }

    private function email(PurchaseOrder $order, Supplier $supplier, Tenant $tenant): bool
    {
        $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
        $items = PurchaseOrderItem::query()->where('purchaseOrderId', $order->id)->orderBy('createdAt')->get();
        $products = Product::query()->whereIn('id', $items->pluck('productId'))->get(['id', 'name', 'sku'])->keyBy('id');
        $ref = strtoupper(substr($order->id, -8));
        $shop = (string) $tenant->name;

        $summary = [];
        foreach ($items as $item) {
            $product = $products->get($item->productId);
            $label = $product ? $product->name.($product->sku ? ' ('.$product->sku.')' : '') : 'Item';
            $summary[$label] = $item->quantityOrdered.' × '.$money($item->unitCost).' = '.$money($item->lineTotal);
        }
        $summary['Total'] = $money($order->totalCost);

        $paragraphs = [
            'Hello '.e((string) $supplier->name).',',
            e($shop).' would like to order the items below.',
        ];
        if ($order->urgency === 'OUT_OF_STOCK' || $order->urgency === 'CRITICAL') {
            $paragraphs[] = '<strong>This is urgent.</strong> Some of these items have run out.';
        }
        if ($order->notes) {
            $paragraphs[] = 'Note: '.e((string) $order->notes);
        }

        $contact = collect([$tenant->phone ? 'call '.$tenant->phone : null, $tenant->email ? 'reply to this email' : null])->filter()->implode(' or ');

        return AuthMail::send(
            (string) $supplier->contactEmail,
            (string) $supplier->name,
            'Purchase order #'.$ref.' from '.$shop,
            [
                'heading' => 'Purchase order #'.$ref,
                'kicker' => 'From '.$shop,
                'paragraphs' => $paragraphs,
                'summary' => $summary,
                'summaryTitle' => 'Items',
                'note' => $contact !== '' ? 'To confirm or ask a question, '.$contact.'.' : null,
                'signoff' => $shop,
            ],
            replyTo: ['address' => (string) $tenant->email, 'name' => $shop],
        );
    }
}
