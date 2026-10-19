<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Support\AuthMail;
use App\Support\PhoneNumber;

class SupplierMessenger
{
    /**
     * Channels a supplier can be reached on.
     *
     * @return list<string>
     */
    public static function channels(?Supplier $supplier): array
    {
        $channels = [];
        if ($supplier && filter_var((string) $supplier->contactEmail, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'email';
        }
        if ($supplier && PhoneNumber::e164((string) $supplier->contactWhatsapp)) {
            $channels[] = 'whatsapp';
        }

        return $channels;
    }

    /**
     * Sends the purchase order on every available channel. Returns a joined label, or null when nothing went out.
     */
    public function sendPurchaseOrder(PurchaseOrder $order, Supplier $supplier, Tenant $tenant): ?string
    {
        $sent = [];
        if (in_array('email', self::channels($supplier), true) && $this->email($order, $supplier, $tenant)) {
            $sent[] = 'email';
        }
        if (in_array('whatsapp', self::channels($supplier), true) && $this->whatsapp($order, $supplier, $tenant)) {
            $sent[] = 'whatsapp';
        }

        return $sent === [] ? null : implode('+', $sent);
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

    private function whatsapp(PurchaseOrder $order, Supplier $supplier, Tenant $tenant): bool
    {
        $ref = strtoupper(substr($order->id, -8));
        $items = PurchaseOrderItem::query()->where('purchaseOrderId', $order->id)->orderBy('createdAt')->get();
        $products = Product::query()->whereIn('id', $items->pluck('productId'))->get(['id', 'name'])->keyBy('id');
        $lines = [];
        foreach ($items as $item) {
            $name = $products->get($item->productId)?->name ?: 'Item';
            $lines[] = $item->quantityOrdered.' × '.$name;
        }

        return app(ZohoWhatsApp::class)->sendPurchaseOrder(
            (string) $supplier->contactWhatsapp,
            [
                'shop' => (string) $tenant->name,
                'supplier' => (string) $supplier->name,
                'order_ref' => $ref,
                'items' => implode(', ', $lines),
                'total' => '₦'.number_format((float) $order->totalCost, 2),
                'owner_phone' => (string) ($tenant->phone ?: ''),
            ],
            (string) $supplier->name,
        );
    }
}
