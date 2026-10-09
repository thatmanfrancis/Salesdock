<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Support\AuthMail;
use App\Support\PlanFeatures;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

class RestockWatcher
{
    public const URGENCY_ORDER = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL', 'OUT_OF_STOCK'];

    /**
     * Drafts (or tops up) an automatic purchase order when a product drops to its low-stock threshold,
     * and clears the product's drafted mark once it is back above the threshold.
     */
    public function check(string $productId): void
    {
        try {
            DB::transaction(fn () => $this->evaluate($productId));
        } catch (\Throwable $e) {
            Log::error('restock check failed', ['productId' => $productId, 'error' => $e->getMessage()]);
        }
    }

    public static function suggestedQuantity(Product $product): int
    {
        if ((int) $product->reorderQty > 0) {
            return (int) $product->reorderQty;
        }

        return max(1, ((int) $product->minThreshold * 2) - max(0, (int) $product->currentStock));
    }

    public static function urgencyFor(Product $product): string
    {
        $stock = (int) $product->currentStock;
        $threshold = (int) $product->minThreshold;
        if ($stock <= 0) {
            return 'OUT_OF_STOCK';
        }
        if ($stock * 2 <= $threshold) {
            return 'HIGH';
        }

        return 'MEDIUM';
    }

    private function evaluate(string $productId): void
    {
        $product = Product::query()->whereKey($productId)->lockForUpdate()->first();
        if (! $product) {
            return;
        }

        $low = $product->isActive && (int) $product->currentStock <= (int) $product->minThreshold;
        if (! $low) {
            if ($product->restockDraftedAt) {
                $product->forceFill(['restockDraftedAt' => null])->saveQuietly();
            }

            return;
        }
        if ($product->restockDraftedAt || ! $product->supplierId) {
            return;
        }

        $tenant = Tenant::query()->find($product->tenantId);
        if (! $tenant?->restockDrafts || ! $this->planAllows($tenant)) {
            return;
        }

        $supplier = Supplier::query()
            ->where('tenantId', $tenant->id)
            ->whereKey($product->supplierId)
            ->lockForUpdate()
            ->first();
        if (! $supplier?->isActive || ! $supplier->restockDrafts) {
            return;
        }

        $order = PurchaseOrder::query()
            ->where('tenantId', $tenant->id)
            ->where('supplierId', $supplier->id)
            ->where('status', 'DRAFT')
            ->where('isAutomatic', true)
            ->latest('createdAt')
            ->first();
        $isNew = ! $order;
        $urgency = self::urgencyFor($product);

        if ($isNew) {
            $order = PurchaseOrder::query()->create([
                'tenantId' => $tenant->id,
                'supplierId' => $supplier->id,
                'status' => 'DRAFT',
                'urgency' => $urgency,
                'totalCost' => 0,
                'notes' => null,
                'isAutomatic' => true,
            ]);
        }

        $hasLine = PurchaseOrderItem::query()
            ->where('purchaseOrderId', $order->id)
            ->where('productId', $product->id)
            ->exists();
        if (! $hasLine) {
            $quantity = self::suggestedQuantity($product);
            $cost = round((float) $product->costPrice, 2);
            PurchaseOrderItem::query()->create([
                'purchaseOrderId' => $order->id,
                'productId' => $product->id,
                'quantityOrdered' => $quantity,
                'quantityReceived' => 0,
                'unitCost' => $cost,
                'lineTotal' => round($cost * $quantity, 2),
                'createdAt' => now(),
            ]);
        }

        $order->forceFill([
            'totalCost' => round((float) PurchaseOrderItem::query()->where('purchaseOrderId', $order->id)->sum('lineTotal'), 2),
            'urgency' => $this->higherUrgency((string) $order->urgency, $urgency),
        ])->save();

        $product->forceFill(['restockDraftedAt' => now()])->saveQuietly();

        $ref = strtoupper(substr($order->id, -8));
        $stockText = (int) $product->currentStock <= 0 ? 'is out of stock' : 'is down to '.(int) $product->currentStock;
        Notification::query()->create([
            'tenantId' => $tenant->id,
            'type' => (int) $product->currentStock <= 0 ? 'OUT_OF_STOCK' : 'LOW_STOCK',
            'title' => 'Restock draft for '.$supplier->name,
            'message' => $product->name.' '.$stockText.'. Review PO #'.$ref.' and send it to '.$supplier->name.'.',
            'entityId' => $order->id,
            'createdAt' => now(),
        ]);

        if ($isNew && $tenant->email) {
            $this->emailOwner($tenant, $supplier, $order, $product, $stockText);
        }
    }

    private function planAllows(Tenant $tenant): bool
    {
        $features = Subscription::query()->with('plan')->where('tenantId', $tenant->id)->first()?->features() ?? [];

        return PlanFeatures::includes($features, ['Supplier Management']);
    }

    private function higherUrgency(string $current, string $candidate): string
    {
        $currentRank = array_search($current, self::URGENCY_ORDER, true);
        $candidateRank = array_search($candidate, self::URGENCY_ORDER, true);

        return ($candidateRank !== false && ($currentRank === false || $candidateRank > $currentRank)) ? $candidate : $current;
    }

    private function emailOwner(Tenant $tenant, Supplier $supplier, PurchaseOrder $order, Product $product, string $stockText): void
    {
        $ref = strtoupper(substr($order->id, -8));
        $email = (string) $tenant->email;
        $name = (string) $tenant->name;
        $url = route('inventory.orders.show', $order);
        $supplierName = (string) $supplier->name;
        $productName = (string) $product->name;

        defer(fn () => AuthMail::send($email, $name, 'Restock draft ready for '.$supplierName, [
            'heading' => 'Time to restock',
            'kicker' => 'PO #'.$ref.' for '.$supplierName,
            'paragraphs' => [
                e($productName).' '.e($stockText).', so we drafted a purchase order to '.e($supplierName).'.',
                'Nothing has been sent yet. Review the quantities, make any changes, then send it to the supplier.',
            ],
            'url' => $url,
            'label' => 'Review purchase order',
            'note' => 'Other products from '.$supplierName.' that run low will be added to this same draft until you send it.',
        ]));
    }
}
