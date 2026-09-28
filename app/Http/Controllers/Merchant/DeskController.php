<?php

namespace App\Http\Controllers\Merchant;

use App\Models\CatalogueProduct;
use App\Models\Customer;
use App\Models\Hold;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Notification;
use App\Models\Refund;
use App\Models\SalesLedger;
use App\Models\SalesLedgerItem;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Services\SaleRecorder;
use App\Support\Approval;
use App\Support\Permissions;
use App\Support\ProductCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Validation\ValidationException;

class DeskController extends MerchantController
{
    public function dashboard(Request $request)
    {
        $user = $this->user()->loadMissing('role', 'tenant');
        $tenantId = $user->tenantId;
        if (! $tenantId) {
            return redirect()->route('admin.home');
        }

        $role = $user->isSuperAdmin ? 'SUPER_ADMIN' : ($user->role?->role ?? 'CASHIER');
        $permissions = Permissions::normalize($user->role?->permissions ?? []);
        $owner = $user->role?->name === 'Owner' || $user->isSuperAdmin;
        $can = fn (string $key): bool => $owner || ! empty($permissions[$key]);
        $storewide = $role !== 'CASHIER';
        [$start, $end, $period, $periodLabel] = $this->salesWindow($request);
        $sales = $this->orderQuery()->where('status', 'COMPLETED')->whereBetween('createdAt', [$start, $end])->get();

        $stats = [];
        if ($can('canManageOrders') || $role === 'CASHIER') {
            $whose = $storewide ? 'Sales' : 'Your sales';
            $ordersLabel = $storewide ? 'Orders' : 'Your orders';
            $stats[] = ['label' => $whose.' · '.$periodLabel, 'value' => $this->short($sales->sum('netAmount'), true)];
            $stats[] = ['label' => $ordersLabel.' · '.$periodLabel, 'value' => $this->short($sales->count())];
        }
        if ($storewide && ($can('canManageProducts') || $can('canManageInventory'))) {
            $stats[] = ['label' => 'Products', 'value' => $this->short(Product::query()->where('tenantId', $tenantId)->count())];
            $stats[] = ['label' => 'Low stock', 'value' => $this->short(Product::query()->where('tenantId', $tenantId)->whereColumn('currentStock', '<=', 'minThreshold')->count())];
        }
        if ($storewide && $can('canProcessRefunds')) {
            $stats[] = ['label' => 'Open refunds', 'value' => $this->short(Refund::query()->where('tenantId', $tenantId)->where('status', 'PENDING')->count())];
        }
        if ($storewide && $can('canManageStaff')) {
            $stats[] = ['label' => 'Active staff', 'value' => $this->short(\App\Models\User::query()->where('tenantId', $tenantId)->where('isActive', true)->count())];
        }

        $chart = $this->hourChart($sales);

        $orders = ($can('canManageOrders') || $role === 'CASHIER')
            ? $this->orderQuery()->whereBetween('createdAt', [$start, $end])->latest('createdAt')->limit(10)->get()->map(fn ($order) => [
                'when' => $order->createdAt?->format('d M H:i'),
                'status' => $order->status,
                'customer' => $order->customerName ?: 'Walk-in',
                'href' => $can('canManageOrders') ? route('orders.show', $order) : null,
                'net' => $this->short($order->netAmount, true),
            ])
            : collect();
        $top = $storewide && $can('canManageOrders')
            ? $this->topProducts($sales->pluck('id'))->map(fn ($item) => [
                'name' => $item['name'],
                'qty' => $this->short($item['qty']),
                'total' => $this->short($item['total'], true),
            ])
            : null;

        return view('merchant.dashboard', [
            'stats' => $stats,
            'chart' => $chart,
            'orders' => $orders,
            'ordersLink' => $can('canManageOrders'),
            'top' => $top,
            'pos' => $can('canUsePOS') && ! $owner,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
        ]);
    }

    public function pos()
    {
        $tenantId = $this->tenantId();

        $holds = Hold::query()->where('tenantId', $tenantId)->where('status', 'ACTIVE')->when($this->cashierOnly(), fn ($query) => $query->where('cashierId', $this->user()->id))->latest()->limit(12)->get();
        $items = OrderItem::query()->whereIn('orderId', $holds->pluck('orderId'))->get()->groupBy('orderId');
        $vat = \App\Models\VatSettings::query()->where('tenantId', $tenantId)->first();

        return view('merchant.pos', [
            'products' => Product::query()->where('tenantId', $tenantId)->where('isActive', true)->orderBy('name')->get(),
            'holds' => $holds->map(fn ($hold) => [
                'id' => $hold->id,
                'reference' => $hold->reference ?: 'Unnamed hold',
                'count' => $items->get($hold->orderId, collect())->count(),
                'total' => $this->money($items->get($hold->orderId, collect())->sum('lineTotal')),
                'expires' => $hold->expiresAt?->format('d M, H:i'),
            ]),
            'resume' => session('pos_cart', []),
            'resumeHold' => session('pos_hold'),
            'vatRate' => (float) ($vat->globalVatRate ?? 7.5),
            'vatInclusive' => (bool) ($vat->isInclusive ?? false),
            'needsPin' => ! Approval::canAuthorize($this->user()),
        ]);
    }

    public function sell(Request $request, SaleRecorder $sales)
    {
        $data = $request->validate([
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['required', 'in:CASH,TRANSFER,CARD,POS_TERMINAL'],
            'customerName' => ['nullable', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
            'hold_label' => ['nullable', 'string', 'max:120'],
        ]);

        $account = $this->user()->loadMissing('role');
        $permissions = Permissions::normalize($account->role?->permissions ?? []);
        if (($data['discount'] ?? 0) > 0 && empty($permissions['canApplyDiscounts']) && $account->role?->name !== 'Owner') {
            return back()->withErrors(['discount' => 'You cannot apply discounts.']);
        }

        $lines = [];
        foreach ($data['qty'] as $productId => $qty) {
            if ((int) $qty > 0) {
                $lines[] = ['productId' => $productId, 'qty' => (int) $qty];
            }
        }

        $hold = $request->filled('hold_label');
        $order = $sales->sell($this->tenantId(), $lines, [
            'branchId' => $this->user()->branchId,
            'cashierId' => $this->user()->id,
            'channel' => 'POS',
            'method' => $data['method'],
            'discount' => $data['discount'] ?? 0,
            'customerName' => $data['customerName'] ?? null,
            'customerPhone' => $data['customerPhone'] ?? null,
            'complete' => ! $hold,
            'notes' => $request->input('hold_label'),
            'resumeHoldId' => $this->resumeHoldId($request),
        ]);

        session()->forget(['pos_cart', 'pos_hold']);

        if ($hold) {
            Hold::query()->create([
                'tenantId' => $this->tenantId(),
                'orderId' => $order->id,
                'cashierId' => $this->user()->id,
                'reference' => $request->input('hold_label') ?: 'Held sale',
                'status' => 'ACTIVE',
                'expiresAt' => now()->addHours(4),
            ]);

            return redirect()->route('pos')->with('status', 'Sale held. The items are set aside for 4 hours.');
        }

        $ref = strtoupper(substr((string) ($order->paymentRef ?: $order->id), -8));
        $total = number_format((float) $order->netAmount, 2);
        $orderUrl = route('orders.show', $order);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'orderId' => $order->id,
                'ref' => $ref,
                'total' => '₦' . $total,
                'method' => $data['method'],
                'orderUrl' => $orderUrl,
            ]);
        }

        return redirect($orderUrl)->with('status', 'Sale completed.');
    }

    public function restoreHold(Hold $hold)
    {
        $this->guardHold($hold);
        $lines = OrderItem::query()->where('orderId', $hold->orderId)->get()->map(fn ($item) => [
            'id' => $item->productId,
            'qty' => (int) $item->quantity,
        ])->values();

        session([
            'pos_cart' => $lines,
            'pos_hold' => $hold->id,
        ]);

        return redirect()->route('pos')->with('status', 'Hold is back in the cart.');
    }

    public function dropHold(Hold $hold, SaleRecorder $sales)
    {
        $this->guardHold($hold);
        $sales->releaseHold($hold, 'CANCELLED', 'Hold dropped at POS');
        if (session('pos_hold') === $hold->id) {
            session()->forget(['pos_cart', 'pos_hold']);
        }

        return redirect()->route('pos')->with('status', 'Hold dropped. Those items are available again.');
    }

    public function voidRegister(Request $request, SaleRecorder $sales)
    {
        $holds = Hold::query()->where('tenantId', $this->tenantId())->where('status', 'ACTIVE')->get();
        $actor = $this->user();
        $approver = $actor;

        if ($holds->isNotEmpty()) {
            $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];
            if (! Approval::canAuthorize($actor)) {
                $approver = Approval::authorize($request, $this->tenantId(), $actor->branchId);
            }

            foreach ($holds as $hold) {
                $sales->releaseHold($hold, 'CANCELLED', $reason);
            }

            Approval::log($request, $this->tenantId(), $actor->id, $approver->id, 'CASHIER_VOID', [
                'reason' => $reason,
                'holdIds' => $holds->pluck('id')->all(),
                'authorizedBy' => $approver->name,
                'authorizedRole' => $approver->role?->name ?: $approver->role?->role,
            ]);
        }

        session()->forget(['pos_cart', 'pos_hold']);

        return redirect()->route('pos')->with('status', $holds->isEmpty()
            ? 'Cart cleared.'
            : 'Cart cleared and parked sales cancelled.');
    }

    public function products(Request $request)
    {
        abort_unless($this->managesProducts(), 403);

        $tenantId = $this->tenantId();
        $q = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        [$used, $limit] = $this->productAllowance();
        $perPage = 20;

        $query = Product::query()->where('tenantId', $tenantId)->where('isActive', true);
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'ilike', '%'.$q.'%')
                    ->orWhere('sku', 'ilike', '%'.$q.'%')
                    ->orWhere('barcode', 'ilike', '%'.$q.'%');
            });
        }
        if ($category !== '') {
            $query->where('category', $category);
        }

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));
        $categories = Product::query()->where('tenantId', $tenantId)->where('isActive', true)->distinct()->orderBy('category')->pluck('category');
        $catalogue = trim((string) $request->query('catalogue', ''));
        $catalogueCat = trim((string) $request->query('catalogue_cat', ''));

        // Build catalogue category list for the select
        $catalogueCategories = CatalogueProduct::query()
            ->where('isActive', true)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('merchant.products', [
            'products' => $query->orderBy('name')->forPage($page, $perPage)->get(),
            'q' => $q,
            'category' => $category,
            'categories' => $categories,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '' || $category !== '',
            'used' => $used,
            'limit' => $limit,
            'full' => $used >= $limit,
            'costs' => $this->seesCosts(),
            'suppliers' => Supplier::query()->where('tenantId', $tenantId)->orderBy('name')->pluck('name', 'id'),
            'panel' => (string) $request->query('panel', ''),
            'catalogueCat' => $catalogueCat,
            'catalogueCategories' => $catalogueCategories,
            'catalogue' => $catalogueCat === ''
                ? collect()
                : CatalogueProduct::query()
                    ->where('isActive', true)
                    ->whereRaw('lower(category) = ?', [mb_strtolower($catalogueCat)])
                    ->orderBy('name')
                    ->limit(200)
                    ->get(),
            'catalogueQuery' => $catalogue, // kept for back-compat
        ]);
    }

    public function suggestSku(Request $request)
    {
        abort_unless($this->managesProducts(), 403);
        $name = mb_substr(trim((string) $request->query('name', '')), 0, 255);
        $except = (string) $request->query('except', '');

        return response()->json([
            'sku' => $this->skuFromName($name !== '' ? $name : 'ITEM', $except !== '' ? $except : null),
        ]);
    }

    public function storeProduct(Request $request)
    {
        abort_unless($this->managesProducts(), 403);
        $data = $this->validatedProduct($request);
        $data['imageUrl'] = $this->storedImage($request);
        $this->saveNewProduct($data);

        return redirect()->route('products')->with('status', 'Product saved.');
    }

    public function bulkProducts(Request $request)
    {
        abort_unless($this->managesProducts(), 403);
        $rows = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.sku' => ['nullable', 'string', 'max:120'],
            'rows.*.price' => ['nullable', 'numeric', 'min:0'],
            'rows.*.currentStock' => ['nullable', 'integer', 'min:0'],
            'rows.*.category' => ['nullable', 'string', 'max:120'],
        ])['rows'];

        [$saved, $skipped] = $this->saveMany(collect($rows)->filter(fn ($row) => trim((string) ($row['name'] ?? '')) !== '')->map(fn ($row) => [
            'name' => $row['name'],
            'sku' => $row['sku'] ?: $this->skuFromName($row['name']),
            'price' => $row['price'] ?? 0,
            'currentStock' => $row['currentStock'] ?? 0,
            'category' => $row['category'] ?: 'General',
            'availabilityMode' => 'BOTH',
        ])->values()->all());

        return redirect()->route('products')->with('status', $this->importStatus($saved, $skipped, 'saved'));
    }

    public function productTemplate()
    {
        abort_unless($this->managesProducts(), 403);
        $columns = ['name', 'sku', 'barcode', 'category', 'price'];
        $sample = ['Indomie Chicken', 'INDOMIE', '1234567890123', 'Food', '250'];
        if ($this->seesCosts()) {
            $columns[] = 'cost price';
            $columns[] = 'wholesale price';
            $sample[] = '180';
            $sample[] = '';
        }
        array_push($columns, 'stock', 'min threshold', 'expiry');
        array_push($sample, '40', '5', '');
        $csv = implode(',', $columns)."\n".implode(',', $sample)."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="salesdock-products.csv"',
        ]);
    }

    public function importProducts(Request $request)
    {
        abort_unless($this->managesProducts(), 403);
        $file = $request->validate([
            'file' => ['required', 'file', 'extensions:csv', 'max:2048'],
        ])['file'];
        $parsed = ProductCsv::parse($file->get());
        [$saved, $skipped] = $this->saveMany(collect($parsed)->filter(fn ($row) => ($row['name'] ?? '') !== '')->map(fn ($row) => [
            'name' => $row['name'],
            'sku' => $row['sku'] ?: $this->skuFromName($row['name']),
            'barcode' => $row['barcode'],
            'category' => $row['category'] ?: 'General',
            'price' => $this->amount($row['price'] ?? 0),
            'costPrice' => $this->seesCosts() ? $this->amount($row['costPrice'] ?? 0) : 0,
            'wholesalePrice' => $this->seesCosts() && ($row['wholesalePrice'] ?? '') !== '' ? $this->amount($row['wholesalePrice']) : null,
            'currentStock' => (int) $this->amount($row['currentStock'] ?? 0),
            'minThreshold' => (int) $this->amount($row['minThreshold'] ?? 5),
            'expiresAt' => $row['expiresAt'],
            'availabilityMode' => 'BOTH',
        ])->values()->all());

        return redirect()->route('products')->with('status', $this->importStatus($saved, $skipped, 'imported'));
    }

    public function importCatalogue(Request $request)
    {
        abort_unless($this->managesProducts(), 403);
        $data = $request->validate([
            'catalogueId' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'currentStock' => ['required', 'integer', 'min:0'],
        ]);
        $item = CatalogueProduct::query()->where('isActive', true)->findOrFail($data['catalogueId']);
        if (Product::query()->where('tenantId', $this->tenantId())->where('isActive', true)->where('name', $item->name)->exists()) {
            throw ValidationException::withMessages(['catalogueId' => $item->name.' is already in this shop.']);
        }
        $this->saveNewProduct([
            'name' => $item->name,
            'sku' => $this->skuFromName($item->name),
            'barcode' => $item->barcode,
            'category' => $item->category ?: 'General',
            'description' => $item->description,
            'price' => $data['price'],
            'currentStock' => $data['currentStock'],
            'availabilityMode' => 'BOTH',
        ]);

        return redirect()->route('products')->with('status', $item->name.' added from the catalogue.');
    }

    public function starterCatalogue(Request $request)
    {
        abort_unless($this->managesProducts(), 403);

        [$used, $limit] = $this->productAllowance();
        $remaining = max(0, $limit - $used);
        $businessType = $this->tenantBusinessType();
        $categories   = $this->starterCategoriesFor($businessType);

        $items = collect();
        if ($categories) {
            $items = CatalogueProduct::query()
                ->where('isActive', true)
                ->where(function ($q) use ($categories) {
                    foreach ($categories as $cat) {
                        $q->orWhereRaw('lower(category) = ?', [mb_strtolower($cat)]);
                    }
                })
                ->orderBy('category')
                ->orderBy('name')
                ->limit(max(1, $remaining + 50)) // fetch a bit more so user can pick
                ->get();
        }

        // Mark items already imported by this tenant
        $existingNames = Product::query()
            ->where('tenantId', $this->tenantId())
            ->where('isActive', true)
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower($n))
            ->all();

        return view('merchant.products-starter', [
            'items'        => $items,
            'used'         => $used,
            'limit'        => $limit,
            'remaining'    => $remaining,
            'businessType' => $businessType,
            'existingNames'=> $existingNames,
        ]);
    }

    public function importStarter(Request $request)
    {
        abort_unless($this->managesProducts(), 403);

        $data = $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.id'         => ['required', 'string'],
            'items.*.price'      => ['required', 'numeric', 'min:0.01'],
            'items.*.stock'      => ['nullable', 'integer', 'min:0'],
        ]);

        [$used, $limit] = $this->productAllowance();
        $remaining = max(0, $limit - $used);

        if ($remaining < 1) {
            return back()->withErrors(['items' => 'Your plan limit of '.$limit.' products has been reached.']);
        }

        $rows = collect($data['items'])->take($remaining);
        $saved = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $item = CatalogueProduct::query()->where('isActive', true)->find($row['id']);
            if (! $item) { $skipped++; continue; }

            if (Product::query()->where('tenantId', $this->tenantId())->where('isActive', true)->where('name', $item->name)->exists()) {
                $skipped++;
                continue;
            }

            try {
                $this->saveNewProduct([
                    'name'             => $item->name,
                    'barcode'          => $item->barcode,
                    'category'         => $item->category ?: 'General',
                    'description'      => $item->description,
                    'price'            => (float) $row['price'],
                    'currentStock'     => (int) ($row['stock'] ?? 0),
                    'availabilityMode' => 'BOTH',
                ]);
                $saved++;
            } catch (ValidationException) {
                $skipped++;
                break; // hit plan limit mid-loop
            }
        }

        $ignored = max(0, count($data['items']) - $remaining);
        $msg = $this->importStatus($saved, $skipped, 'imported');
        if ($ignored > 0) {
            $msg .= ' '.$ignored.' item'.($ignored === 1 ? '' : 's').' skipped — plan limit reached.';
        }

        return redirect()->route('products')->with('status', $msg);
    }

    /** Map a business type string to catalogue category names. */
    private function starterCategoriesFor(?string $businessType): array
    {
        if (! $businessType) {
            return [];
        }

        $map = [
            'Supermarket'  => ['Food', 'Grocery', 'Beverages', 'General', 'Household'],
            'Fashion'      => ['Fashion', 'Clothing', 'Accessories', 'Footwear', 'Apparel'],
            'Electronics'  => ['Electronics', 'Gadgets', 'Accessories', 'Cables'],
            'Pharmacy'     => ['Pharmacy', 'Medicine', 'Health', 'Personal Care', 'Wellness'],
            'Restaurant'   => ['Food', 'Beverages', 'Drinks', 'Snacks'],
            'Grocery'      => ['Grocery', 'Food', 'Beverages', 'Household'],
            'Beauty'       => ['Beauty', 'Cosmetics', 'Skincare', 'Hair Care', 'Personal Care'],
            'Hardware'     => ['Hardware', 'Tools', 'Building', 'Electrical'],
            'General'      => ['General'],
        ];

        return $map[$businessType] ?? ['General'];
    }

    /** Retrieve the business type for the current user's registration. */
    private function tenantBusinessType(): ?string
    {
        $email = $this->user()->email;

        return \App\Models\TenantRegistration::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->value('businessType');
    }

    public function showProduct(Request $request, Product $product)
    {
        abort_unless($this->managesProducts(), 403);
        abort_unless($product->tenantId === $this->tenantId(), 404);
        $promoId = $product->activePromoId;

        return view('merchant.product', [
            'product' => $product,
            'tab' => in_array($request->query('tab'), ['info', 'variants', 'stock', 'promo'], true) ? $request->query('tab') : 'info',
            'costs' => $this->seesCosts(),
            'suppliers' => Supplier::query()->where('tenantId', $product->tenantId)->orderBy('name')->pluck('name', 'id'),
            'variants' => ProductVariant::query()->where('productId', $product->id)->orderBy('name')->get(),
            'movements' => StockMovement::query()->where('productId', $product->id)->latest('createdAt')->limit(40)->get(),
            'promos' => Promotion::query()->where('tenantId', $product->tenantId)->where('isActive', true)->orderBy('name')->pluck('name', 'id'),
            'promo' => $promoId ? Promotion::query()->find($promoId) : null,
            'categories' => Product::query()->where('tenantId', $product->tenantId)->where('isActive', true)->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function updateProduct(Request $request, Product $product)
    {
        abort_unless($this->managesProducts(), 403);
        abort_unless($product->tenantId === $this->tenantId(), 404);
        $data = $this->validatedProduct($request);
        if (! $this->seesCosts()) {
            unset($data['costPrice'], $data['wholesalePrice']);
        }
        unset($data['currentStock']);
        $image = $this->storedImage($request);
        if ($image) {
            $data['imageUrl'] = $image;
        }
        $this->guardSku($product->tenantId, $data['sku'], $product->id);
        $this->guardBarcode($product->tenantId, $data['barcode'] ?? null, $product->id);
        $product->update($data);
        Approval::log($request, $product->tenantId, $this->user()->id, $this->user()->id, 'PRODUCT_UPDATED', [
            'productId' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
        ]);

        return back()->with('status', 'Product updated.');
    }

    public function deactivateProduct(Request $request, Product $product)
    {
        abort_unless($this->managesProducts(), 403);
        abort_unless($product->tenantId === $this->tenantId(), 404);
        $product->forceFill(['isActive' => false, 'availabilityMode' => 'DISABLED'])->save();
        Approval::log($request, $product->tenantId, $this->user()->id, $this->user()->id, 'PRODUCT_DELETED', [
            'productId' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
        ]);

        return redirect()->route('products')->with('status', $product->name.' is no longer for sale.');
    }

    public function adjustProductStock(Request $request, Product $product)
    {
        abort_unless($this->managesProducts(), 403);
        abort_unless($product->tenantId === $this->tenantId(), 404);
        $data = $request->validate([
            'type' => ['required', 'in:ADJUSTMENT_IN,ADJUSTMENT_OUT'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($product, $data) {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->first();
            $before = (int) $locked->currentStock;
            $delta = $data['type'] === 'ADJUSTMENT_IN' ? (int) $data['quantity'] : -((int) $data['quantity']);
            $after = max(0, $before + $delta);
            $locked->forceFill(['currentStock' => $after])->save();
            StockMovement::query()->create([
                'productId' => $locked->id,
                'tenantId' => $locked->tenantId,
                'type' => $data['type'],
                'quantity' => $after - $before,
                'beforeQty' => $before,
                'afterQty' => $after,
                'notes' => $data['notes'] ?? null,
                'createdAt' => now(),
            ]);
        });

        return back()->with('status', 'Stock updated.');
    }

    public function attachProductPromo(Request $request, Product $product)
    {
        abort_unless($this->managesProducts(), 403);
        abort_unless($product->tenantId === $this->tenantId(), 404);
        $promoId = $request->validate(['activePromoId' => ['nullable', 'string']])['activePromoId'] ?? null;
        $promoId = $promoId !== '' ? $promoId : null;
        if ($promoId) {
            abort_unless(Promotion::query()->where('tenantId', $product->tenantId)->whereKey($promoId)->exists(), 404);
        }
        $product->forceFill([
            'activePromoId' => $promoId,
            'isDiscounted' => (bool) $promoId,
        ])->save();

        return back()->with('status', $promoId ? 'Promotion attached.' : 'Promotion removed.');
    }

    public function storeVariant(Request $request, Product $product)
    {
        abort_unless($this->managesProducts(), 403);
        abort_unless($product->tenantId === $this->tenantId(), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:120'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'costPrice' => ['nullable', 'numeric', 'min:0'],
            'currentStock' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->guardBarcode($product->tenantId, $data['barcode'] ?? null);
        ProductVariant::query()->create($data + [
            'productId' => $product->id,
            'tenantId' => $product->tenantId,
            'attributes' => [],
            'costPrice' => $this->seesCosts() ? ($data['costPrice'] ?? 0) : 0,
            'currentStock' => $data['currentStock'] ?? 0,
        ]);

        return back()->with('status', 'Variant added.');
    }

    public function orders(Request $request)
    {
        abort_unless($this->managesOrders(), 403);

        $shop = ! $this->cashierOnly();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $channel = $shop ? (string) $request->query('channel', '') : '';
        $cashier = $shop ? (string) $request->query('cashier', '') : '';
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        $perPage = 20;

        $query = $this->orderQuery();
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('customerName', 'ilike', '%'.$q.'%')
                    ->orWhere('customerPhone', 'ilike', '%'.$q.'%')
                    ->orWhere('paymentRef', 'ilike', '%'.$q.'%');
            });
        }
        if (in_array($status, ['PENDING', 'RESERVED', 'COMPLETED', 'CANCELLED', 'RETURNED'], true)) {
            $query->where('status', $status);
        }
        if (in_array($channel, ['POS', 'ONLINE', 'WHATSAPP', 'INSTAGRAM', 'LINK'], true)) {
            $query->where('channel', $channel);
        }
        if ($cashier !== '') {
            $query->where('cashierId', $cashier);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $query->where('createdAt', '>=', \Illuminate\Support\Carbon::parse($from)->startOfDay());
        } else {
            $from = '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $query->where('createdAt', '<=', \Illuminate\Support\Carbon::parse($to)->endOfDay());
        } else {
            $to = '';
        }

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));
        $orders = $query->latest('createdAt')->forPage($page, $perPage)->get();
        $cashiers = $shop
            ? \App\Models\User::query()->where('tenantId', $this->tenantId())->orderBy('name')->pluck('name', 'id')
            : collect();

        return view('merchant.orders', [
            'orders' => $orders,
            'shop' => $shop,
            'names' => $cashiers,
            'q' => $q,
            'status' => $status,
            'channel' => $channel,
            'cashier' => $cashier,
            'from' => $from,
            'to' => $to,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '' || $status !== '' || $channel !== '' || $cashier !== '' || $from !== '' || $to !== '',
        ]);
    }

    public function showOrder(Order $order)
    {
        abort_unless($this->managesOrders(), 403);
        $this->guardOrder($order);

        $items = OrderItem::query()->where('orderId', $order->id)->get();
        $names = Product::query()->whereIn('id', $items->pluck('productId'))->pluck('name', 'id');
        $shop = ! $this->cashierOnly();
        $choices = collect($this->orderTransitions($order->status))
            ->reject(fn ($status) => $status === 'CANCELLED')
            ->mapWithKeys(fn ($status) => [$status => ucfirst(strtolower($status))])
            ->all();

        return view('merchant.order', [
            'order' => $order,
            'items' => $items,
            'names' => $names,
            'transactions' => Transaction::query()->where('orderId', $order->id)->latest('createdAt')->get(),
            'cashier' => $order->cashierId ? \App\Models\User::query()->whereKey($order->cashierId)->value('name') : null,
            'ref' => strtoupper(substr((string) ($order->paymentRef ?: $order->id), -8)),
            'shop' => $shop,
            'open' => $shop && ! in_array($order->status, ['COMPLETED', 'CANCELLED', 'RETURNED'], true),
            'choices' => ['' => 'Select status…'] + $choices,
            'needsPin' => ! Approval::canAuthorize($this->user()),
            'canRequestRefund' => $this->canRequestRefund($order),
        ]);
    }

    public function completeOrder(Order $order, SaleRecorder $sales)
    {
        abort_unless($this->managesOrders() && ! $this->cashierOnly(), 403);
        $this->guardOrder($order);
        abort_unless($order->status === 'PENDING', 422);
        $this->finishPending($order, $sales, 'HOLD-'.$order->id);

        return back()->with('status', 'Held sale completed.');
    }

    public function orderStatus(Request $request, Order $order, SaleRecorder $sales)
    {
        abort_unless($this->managesOrders() && ! $this->cashierOnly(), 403);
        $this->guardOrder($order);
        $data = $request->validate([
            'status' => ['required', 'in:RESERVED,COMPLETED,CANCELLED,RETURNED'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        if (! in_array($data['status'], $this->orderTransitions($order->status), true)) {
            throw ValidationException::withMessages([
                'status' => 'That status change is not allowed from '.$order->status.'.',
            ]);
        }

        if ($order->status === 'PENDING' && $data['status'] === 'COMPLETED') {
            $this->finishPending($order, $sales, 'ORDER-'.$order->id);

            return back()->with('status', 'Order completed.');
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        DB::transaction(function () use ($request, $order, $data, $reason) {
            $this->applyOrderStock($order, $data['status']);
            $order->forceFill([
                'status' => $data['status'],
                'cancelReason' => $data['status'] === 'CANCELLED' ? ($reason !== '' ? $reason : null) : $order->cancelReason,
                'cancelledAt' => $data['status'] === 'CANCELLED' ? now() : $order->cancelledAt,
                'cancelRequested' => $data['status'] === 'CANCELLED' ? false : $order->cancelRequested,
                'returnReason' => $data['status'] === 'RETURNED' ? ($reason !== '' ? $reason : null) : $order->returnReason,
                'returnedAt' => $data['status'] === 'RETURNED' ? now() : $order->returnedAt,
            ])->save();

            if ($data['status'] === 'CANCELLED') {
                Hold::query()->where('orderId', $order->id)->where('status', 'ACTIVE')->update([
                    'status' => 'CANCELLED',
                    'expiredAt' => now(),
                ]);
                Transaction::query()->where('orderId', $order->id)->where('status', 'PENDING')->update(['status' => 'FAILED']);
                $this->orderNotice($request, $order, 'ORDER_CANCELLED', 'Order cancelled', 'Order #'.strtoupper(substr($order->id, -8)).' was cancelled'.($reason !== '' ? ': '.$reason : ''), [
                    'reason' => $reason,
                    'amount' => (float) $order->totalAmount,
                ]);
            }
        });

        return back()->with('status', 'Order updated.');
    }

    public function voidOrder(Request $request, Order $order)
    {
        abort_unless($this->managesOrders() && ! $this->cashierOnly(), 403);
        $this->guardOrder($order);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        if ($order->status !== 'COMPLETED') {
            throw ValidationException::withMessages([
                'reason' => 'Only a completed sale can be voided.',
            ]);
        }

        $actor = $this->user();
        $approver = Approval::canAuthorize($actor)
            ? $actor
            : Approval::authorize($request, $this->tenantId(), $actor->branchId);
        $reason = trim($data['reason']);

        DB::transaction(function () use ($request, $order, $actor, $approver, $reason) {
            $items = OrderItem::query()->where('orderId', $order->id)->get();
            foreach ($items as $item) {
                $product = Product::query()->where('tenantId', $order->tenantId)->lockForUpdate()->find($item->productId);
                if (! $product) {
                    continue;
                }
                $before = (int) $product->currentStock;
                $after = $before + (int) $item->quantity;
                $product->forceFill(['currentStock' => $after])->save();
                StockMovement::query()->create([
                    'productId' => $product->id,
                    'tenantId' => $order->tenantId,
                    'type' => 'RETURN',
                    'quantity' => (int) $item->quantity,
                    'beforeQty' => $before,
                    'afterQty' => $after,
                    'reference' => $order->id,
                    'notes' => 'Void invoice: '.$reason,
                    'createdAt' => now(),
                ]);
            }

            Transaction::query()->where('orderId', $order->id)->where('tenantId', $order->tenantId)->update(['status' => 'REFUNDED']);
            $order->forceFill([
                'status' => 'CANCELLED',
                'cancelReason' => 'VOID: '.$reason,
                'cancelledAt' => now(),
            ])->save();

            $ledgerIds = SalesLedger::query()->where('tenantId', $order->tenantId)->where('orderId', $order->id)->pluck('id');
            if ($ledgerIds->isNotEmpty()) {
                SalesLedgerItem::query()->whereIn('ledgerId', $ledgerIds)->delete();
                SalesLedger::query()->whereIn('id', $ledgerIds)->delete();
            }

            Approval::log($request, $order->tenantId, $actor->id, $approver->id, 'CASHIER_VOID', [
                'orderId' => $order->id,
                'reason' => $reason,
                'totalAmount' => (float) $order->totalAmount,
                'itemCount' => $items->count(),
                'authorizedBy' => $approver->name,
            ]);
            Notification::query()->create([
                'tenantId' => $order->tenantId,
                'type' => 'AUDIT_ALERT',
                'title' => 'Invoice voided',
                'message' => 'Invoice #'.strtoupper(substr($order->id, -8)).' ('.$this->money($order->totalAmount).') was voided — '.$reason,
                'entityId' => $order->id,
                'createdAt' => now(),
            ]);
        });

        return back()->with('status', 'Invoice voided.');
    }

    public function customers(Request $request)
    {
        abort_unless($this->managesCustomers(), 403);

        $q = trim((string) $request->query('q', ''));
        $perPage = 20;
        $query = Customer::query()->where('tenantId', $this->tenantId())->where('isActive', true);
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'ilike', '%'.$q.'%')
                    ->orWhere('phone', 'ilike', '%'.$q.'%')
                    ->orWhere('email', 'ilike', '%'.$q.'%')
                    ->orWhere('whatsapp', 'ilike', '%'.$q.'%');
            });
        }

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('merchant.customers', [
            'customers' => $query->latest('createdAt')->forPage($page, $perPage)->get(),
            'q' => $q,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '',
        ]);
    }

    public function storeCustomer(Request $request)
    {
        abort_unless($this->managesCustomers(), 403);
        $data = $this->customerData($request);
        $this->guardCustomerPhone($this->tenantId(), $data['phone']);
        Customer::query()->create($data + ['tenantId' => $this->tenantId(), 'firstChannel' => 'POS']);

        return redirect()->route('customers')->with('status', 'Customer saved.');
    }

    public function showCustomer(Customer $customer)
    {
        abort_unless($this->managesCustomers(), 403);
        abort_unless($customer->tenantId === $this->tenantId(), 404);

        return $this->screen($customer->name, [
            'intro' => $customer->totalOrders.' orders · '.$this->money($customer->totalSpend).' · '.$customer->loyaltyPoints.' points',
            'form' => [
                'action' => route('customers.update', $customer),
                'method' => 'PUT',
                'submit' => 'Update customer',
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'required' => true, 'value' => $customer->name],
                    ['name' => 'phone', 'label' => 'Phone', 'value' => $customer->phone],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $customer->email],
                    ['name' => 'whatsapp', 'label' => 'WhatsApp', 'value' => $customer->whatsapp],
                    ['name' => 'address', 'label' => 'Address', 'value' => $customer->address],
                    ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'value' => $customer->notes],
                    ['name' => 'isActive', 'label' => 'Active', 'type' => 'select', 'value' => $customer->isActive ? '1' : '0', 'options' => ['1' => 'Yes', '0' => 'No']],
                ],
            ],
            'columns' => ['When', 'Status', 'Channel', 'Net'],
            'rows' => Order::query()->where('customerId', $customer->id)->latest()->limit(30)->get()->map(fn ($order) => [
                optional($order->createdAt)->format('d M H:i'),
                e($order->status),
                e($order->channel),
                $this->link(route('orders.show', $order), $this->money($order->netAmount)),
            ]),
        ]);
    }

    public function updateCustomer(Request $request, Customer $customer)
    {
        abort_unless($this->managesCustomers(), 403);
        abort_unless($customer->tenantId === $this->tenantId(), 404);
        $data = $this->customerData($request);
        if ($request->exists('isActive')) {
            $data['isActive'] = $request->validate(['isActive' => ['required', 'in:0,1']])['isActive'] === '1';
        }
        $this->guardCustomerPhone($customer->tenantId, $data['phone'], $customer->id);
        $customer->update($data);

        return back()->with('status', 'Customer updated.');
    }

    public function destroyCustomer(Customer $customer)
    {
        abort_unless($this->managesCustomers(), 403);
        abort_unless($customer->tenantId === $this->tenantId(), 404);
        $customer->forceFill(['isActive' => false])->save();

        return redirect()->route('customers')->with('status', $customer->name.' was deleted.');
    }

    public function refunds(Request $request)
    {
        abort_unless($this->managesRefunds(), 403);

        $tenantId = $this->tenantId();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $perPage = 20;
        $query = Refund::query()->where('tenantId', $tenantId);
        if (in_array($status, ['PENDING', 'APPROVED', 'REJECTED', 'PROCESSED'], true)) {
            $query->where('status', $status);
        } else {
            $status = '';
        }
        if ($q !== '') {
            $query->where(function ($inner) use ($q, $tenantId) {
                $inner->where('reason', 'ilike', '%'.$q.'%')
                    ->orWhereIn('orderId', Order::query()->where('tenantId', $tenantId)->where(function ($orders) use ($q) {
                        $orders->where('paymentRef', 'ilike', '%'.$q.'%')
                            ->orWhere('id', 'ilike', '%'.$q.'%');
                    })->select('id'));
            });
        }

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));
        $refunds = $query->latest('createdAt')->forPage($page, $perPage)->get();
        $sales = Order::query()->whereIn('id', $refunds->pluck('orderId'))->get()->keyBy('id');

        return view('merchant.refunds', [
            'refunds' => $refunds,
            'sales' => $sales,
            'q' => $q,
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '' || $status !== '',
            'canDecide' => Approval::canAuthorize($this->user()),
            'orderOptions' => $this->refundableOrders(),
        ]);
    }

    public function storeRefund(Request $request)
    {
        $data = $request->validate([
            'orderId' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'form' => ['nullable', 'string'],
        ]);
        $order = Order::query()->where('tenantId', $this->tenantId())->findOrFail($data['orderId']);
        abort_unless($this->canRequestRefund($order), 403);
        $this->guardRefundAmount($order, (float) $data['amount']);

        $refund = Refund::query()->create([
            'tenantId' => $order->tenantId,
            'orderId' => $order->id,
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'status' => 'PENDING',
        ]);
        $actor = $this->user();
        Approval::log($request, $this->tenantId(), $actor->id, $actor->id, 'REFUND_ISSUED', [
            'refundId' => $refund->id,
            'orderId' => $order->id,
            'amount' => (float) $refund->amount,
            'reason' => $refund->reason,
            'status' => 'PENDING',
        ]);

        $back = $this->managesRefunds()
            ? redirect()->route('refunds')
            : back();

        return $back->with('status', 'Refund requested.');
    }

    public function approveRefund(Request $request, Refund $refund)
    {
        return $this->decideRefund($request, $refund, 'APPROVED', 'Refund approved.');
    }

    public function rejectRefund(Request $request, Refund $refund)
    {
        return $this->decideRefund($request, $refund, 'REJECTED', 'Refund rejected.');
    }

    public function processRefund(Request $request, Refund $refund)
    {
        return $this->decideRefund($request, $refund, 'PROCESSED', 'Refund processed.');
    }

    public function inventory(Request $request)
    {
        abort_unless($this->managesInventory(), 403);

        $q = trim((string) $request->query('q', ''));
        $stock = (string) $request->query('stock', '');
        $perPage = 20;
        $query = Product::query()->where('tenantId', $this->tenantId())->where('isActive', true);
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'ilike', '%'.$q.'%')
                    ->orWhere('sku', 'ilike', '%'.$q.'%')
                    ->orWhere('barcode', 'ilike', '%'.$q.'%');
            });
        }
        if ($stock === 'low') {
            $query->where('currentStock', '>', 0)->whereColumn('currentStock', '<=', 'minThreshold');
        } elseif ($stock === 'out') {
            $query->where('currentStock', '<=', 0);
        } else {
            $stock = '';
        }

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('merchant.inventory', [
            'products' => $query->orderBy('name')->forPage($page, $perPage)->get(),
            'q' => $q,
            'stock' => $stock,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '' || $stock !== '',
            'seesCosts' => $this->seesCosts(),
        ]);
    }

    public function adjustStock(Request $request)
    {
        abort_unless($this->managesInventory(), 403);
        $data = $request->validate([
            'productId' => ['required', 'string'],
            'type' => ['required', 'in:ADJUSTMENT_IN,ADJUSTMENT_OUT'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
            'form' => ['nullable', 'string'],
        ]);
        $product = Product::query()->where('tenantId', $this->tenantId())->where('isActive', true)->findOrFail($data['productId']);
        $actor = $this->user();

        DB::transaction(function () use ($product, $data, $request, $actor) {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->first();
            $before = (int) $locked->currentStock;
            $qty = (int) $data['quantity'];
            if ($data['type'] === 'ADJUSTMENT_OUT' && $qty > $before) {
                throw ValidationException::withMessages([
                    'quantity' => 'Only '.$before.' on hand.',
                ]);
            }
            $after = $data['type'] === 'ADJUSTMENT_IN' ? $before + $qty : $before - $qty;
            $locked->forceFill(['currentStock' => $after])->save();
            StockMovement::query()->create([
                'productId' => $locked->id,
                'tenantId' => $locked->tenantId,
                'type' => $data['type'],
                'quantity' => $after - $before,
                'beforeQty' => $before,
                'afterQty' => $after,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'createdAt' => now(),
            ]);
            Approval::log($request, $locked->tenantId, $actor->id, $actor->id, 'STOCK_ADJUST', [
                'productId' => $locked->id,
                'sku' => $locked->sku,
                'type' => $data['type'],
                'beforeQty' => $before,
                'afterQty' => $after,
            ]);
        });

        return back()->with('status', 'Stock updated.');
    }

    public function suppliers(Request $request)
    {
        abort_unless($this->managesSuppliers(), 403);

        $q = trim((string) $request->query('q', ''));
        $perPage = 20;
        $query = Supplier::query()->where('tenantId', $this->tenantId())->where('isActive', true);
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'ilike', '%'.$q.'%')
                    ->orWhere('contactEmail', 'ilike', '%'.$q.'%')
                    ->orWhere('contactPhone', 'ilike', '%'.$q.'%')
                    ->orWhere('contactWhatsapp', 'ilike', '%'.$q.'%');
            });
        }

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('merchant.suppliers', [
            'suppliers' => $query->orderBy('name')->forPage($page, $perPage)->get(),
            'q' => $q,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '',
        ]);
    }

    public function storeSupplier(Request $request)
    {
        abort_unless($this->managesSuppliers(), 403);
        Supplier::query()->create($this->supplierData($request) + ['tenantId' => $this->tenantId(), 'isActive' => true]);

        return redirect()->route('suppliers')->with('status', 'Supplier saved.');
    }

    public function purchaseOrders(Request $request)
    {
        abort_unless($this->managesInventory(), 403);

        $tenantId = $this->tenantId();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $perPage = 20;
        $query = PurchaseOrder::query()->where('tenantId', $tenantId);
        if (in_array($status, ['DRAFT', 'SENT', 'RECEIVED', 'CANCELLED'], true)) {
            $query->where('status', $status);
        } else {
            $status = '';
        }
        if ($q !== '') {
            $query->whereIn('supplierId', Supplier::query()->where('tenantId', $tenantId)->where('name', 'ilike', '%'.$q.'%')->select('id'));
        }

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));
        $orders = $query->latest('createdAt')->forPage($page, $perPage)->get();
        $names = Supplier::query()->whereIn('id', $orders->pluck('supplierId'))->pluck('name', 'id');

        return view('merchant.purchase-orders', [
            'orders' => $orders,
            'names' => $names,
            'q' => $q,
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '' || $status !== '',
            'suppliers' => Supplier::query()->where('tenantId', $tenantId)->where('isActive', true)->orderBy('name')->pluck('name', 'id'),
            'products' => Product::query()->where('tenantId', $tenantId)->where('isActive', true)->orderBy('name')->get(['id', 'name', 'sku']),
        ]);
    }

    public function storePurchaseOrder(Request $request)
    {
        abort_unless($this->managesInventory(), 403);
        $items = collect($request->input('items', []))
            ->filter(fn ($row) => trim((string) ($row['productId'] ?? '')) !== '')
            ->values()
            ->all();
        $request->merge(['items' => $items]);
        $data = $request->validate([
            'supplierId' => ['required', 'string'],
            'urgency' => ['required', 'in:LOW,MEDIUM,HIGH,CRITICAL,OUT_OF_STOCK'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unitCost' => ['required', 'numeric', 'min:0'],
            'form' => ['nullable', 'string'],
        ], [
            'items.required' => 'Add at least one product.',
            'items.min' => 'Add at least one product.',
        ]);
        $tenantId = $this->tenantId();
        $supplier = Supplier::query()->where('tenantId', $tenantId)->findOrFail($data['supplierId']);
        $productIds = collect($data['items'])->pluck('productId')->unique();
        $known = Product::query()->where('tenantId', $tenantId)->where('isActive', true)->whereIn('id', $productIds)->pluck('id');
        if ($known->count() !== $productIds->count()) {
            throw ValidationException::withMessages(['items' => 'Choose products from this shop.']);
        }

        $actor = $this->user();
        $po = DB::transaction(function () use ($data, $tenantId, $supplier, $request, $actor) {
            $lines = collect($data['items'])->map(function ($row) {
                $qty = (int) $row['quantity'];
                $cost = round((float) $row['unitCost'], 2);

                return [
                    'productId' => $row['productId'],
                    'quantityOrdered' => $qty,
                    'unitCost' => $cost,
                    'lineTotal' => round($cost * $qty, 2),
                ];
            });
            $po = PurchaseOrder::query()->create([
                'tenantId' => $tenantId,
                'supplierId' => $supplier->id,
                'urgency' => $data['urgency'],
                'totalCost' => round($lines->sum('lineTotal'), 2),
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'status' => 'DRAFT',
            ]);
            foreach ($lines as $line) {
                PurchaseOrderItem::query()->create($line + [
                    'purchaseOrderId' => $po->id,
                    'quantityReceived' => 0,
                    'createdAt' => now(),
                ]);
            }
            Approval::log($request, $tenantId, $actor->id, $actor->id, 'PO_CREATED', [
                'poId' => $po->id,
                'supplierId' => $supplier->id,
                'totalCost' => (float) $po->totalCost,
            ]);

            return $po;
        });

        return redirect()->route('inventory.orders.show', $po)->with('status', 'Purchase order drafted.');
    }

    public function showPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->guardPurchaseOrder($purchaseOrder);
        $items = PurchaseOrderItem::query()->where('purchaseOrderId', $purchaseOrder->id)->get();
        $supplier = Supplier::query()->whereKey($purchaseOrder->supplierId)->first();

        return view('merchant.purchase-order', [
            'order' => $purchaseOrder,
            'items' => $items,
            'names' => Product::query()->whereIn('id', $items->pluck('productId'))->pluck('name', 'id'),
            'supplier' => $supplier,
            'ref' => strtoupper(substr($purchaseOrder->id, -8)),
        ]);
    }

    public function sendPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->guardPurchaseOrder($purchaseOrder);
        if ($purchaseOrder->status !== 'DRAFT') {
            return back()->withErrors(['po' => 'Only a draft can be sent.']);
        }
        $purchaseOrder->forceFill(['status' => 'SENT', 'sentAt' => now()])->save();
        $actor = $this->user();
        Approval::log($request, $purchaseOrder->tenantId, $actor->id, $actor->id, 'PO_SENT', [
            'poId' => $purchaseOrder->id,
            'status' => 'SENT',
        ]);

        return back()->with('status', 'Purchase order sent.');
    }

    public function cancelPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->guardPurchaseOrder($purchaseOrder);
        if (! in_array($purchaseOrder->status, ['DRAFT', 'SENT'], true)) {
            return back()->withErrors(['po' => 'That purchase order can no longer be cancelled.']);
        }
        $purchaseOrder->forceFill(['status' => 'CANCELLED'])->save();
        $actor = $this->user();
        Approval::log($request, $purchaseOrder->tenantId, $actor->id, $actor->id, 'PO_CREATED', [
            'poId' => $purchaseOrder->id,
            'status' => 'CANCELLED',
        ]);

        return back()->with('status', 'Purchase order cancelled.');
    }

    public function receivePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->guardPurchaseOrder($purchaseOrder);
        if ($purchaseOrder->status !== 'SENT') {
            return back()->withErrors(['po' => 'Only a sent purchase order can be received.']);
        }
        $items = PurchaseOrderItem::query()->where('purchaseOrderId', $purchaseOrder->id)->get();
        $received = $request->input('received', []);
        foreach ($items as $item) {
            $qty = $received[$item->id] ?? 0;
            if (! is_numeric($qty) || (int) $qty < 0 || (int) $qty > (int) $item->quantityOrdered - (int) $item->quantityReceived) {
                throw ValidationException::withMessages([
                    'received.'.$item->id => 'Receive no more than what is still outstanding.',
                ]);
            }
        }

        $actor = $this->user();
        DB::transaction(function () use ($purchaseOrder, $items, $received, $request, $actor) {
            foreach ($items as $item) {
                $qty = (int) ($received[$item->id] ?? 0);
                if ($qty < 1) {
                    continue;
                }
                $product = Product::query()->whereKey($item->productId)->lockForUpdate()->first();
                if (! $product) {
                    continue;
                }
                $before = (int) $product->currentStock;
                $after = $before + $qty;
                $product->forceFill(['currentStock' => $after])->save();
                $item->forceFill(['quantityReceived' => (int) $item->quantityReceived + $qty])->save();
                StockMovement::query()->create([
                    'productId' => $product->id,
                    'tenantId' => $product->tenantId,
                    'type' => 'PURCHASE_RECEIVED',
                    'quantity' => $qty,
                    'beforeQty' => $before,
                    'afterQty' => $after,
                    'reference' => $purchaseOrder->id,
                    'notes' => 'PO received: '.$purchaseOrder->id,
                    'createdAt' => now(),
                ]);
            }
            $purchaseOrder->forceFill(['status' => 'RECEIVED', 'receivedAt' => now()])->save();
            Approval::log($request, $purchaseOrder->tenantId, $actor->id, $actor->id, 'PO_RECEIVED', [
                'poId' => $purchaseOrder->id,
            ]);
        });

        return back()->with('status', 'Stock received.');
    }

    public function showSupplier(Supplier $supplier)
    {
        abort_unless($this->managesSuppliers(), 403);
        abort_unless($supplier->tenantId === $this->tenantId() && $supplier->isActive, 404);

        return view('merchant.supplier', ['supplier' => $supplier]);
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        abort_unless($this->managesSuppliers(), 403);
        abort_unless($supplier->tenantId === $this->tenantId(), 404);
        $supplier->update($this->supplierData($request));

        return back()->with('status', 'Supplier updated.');
    }

    public function destroySupplier(Supplier $supplier)
    {
        abort_unless($this->managesSuppliers(), 403);
        abort_unless($supplier->tenantId === $this->tenantId(), 404);
        $supplier->forceFill(['isActive' => false])->save();

        return redirect()->route('suppliers')->with('status', $supplier->name.' was deactivated.');
    }

    public function promotions(Request $request)
    {
        abort_unless($this->managesPromotions(), 403);

        $tenantId = $this->tenantId();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $perPage = 20;
        $query = Promotion::query()->where('tenantId', $tenantId);
        if ($status === 'active') {
            $query->where('isActive', true);
        } elseif ($status === 'inactive') {
            $query->where('isActive', false);
        } else {
            $status = '';
        }
        if ($q !== '') {
            $query->where(function ($inner) use ($q, $tenantId) {
                $inner->where('name', 'ilike', '%'.$q.'%')
                    ->orWhereIn('productId', Product::query()->where('tenantId', $tenantId)->where('name', 'ilike', '%'.$q.'%')->select('id'));
            });
        }

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));
        $promotions = $query->latest('createdAt')->forPage($page, $perPage)->get();
        $names = Product::query()->whereIn('id', $promotions->pluck('productId')->filter())->pluck('name', 'id');

        return view('merchant.promotions', [
            'promotions' => $promotions,
            'names' => $names,
            'catalog' => Product::query()->where('tenantId', $tenantId)->where('isActive', true)->orderBy('name')->get(['id', 'name', 'sku']),
            'q' => $q,
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '' || $status !== '',
        ]);
    }

    public function storePromotion(Request $request)
    {
        abort_unless($this->managesPromotions(), 403);
        $data = $this->promotionData($request);
        $open = $data['windowOpen'];
        unset($data['windowOpen']);
        $promo = Promotion::query()->create($data + [
            'tenantId' => $this->tenantId(),
            'isActive' => $open,
            'createdBy' => $this->user()->id,
        ]);
        $actor = $this->user();
        Approval::log($request, $promo->tenantId, $actor->id, $actor->id, 'PROMO_CREATED', [
            'promoId' => $promo->id,
            'name' => $promo->name,
            'productId' => $promo->productId,
        ]);

        return redirect()->route('promotions')->with('status', 'Promotion saved.');
    }

    public function updatePromotion(Request $request, Promotion $promotion)
    {
        $this->guardPromotion($promotion);
        $data = $this->promotionData($request);
        unset($data['windowOpen']);
        $promotion->update($data);

        return back()->with('status', 'Promotion updated.');
    }

    public function togglePromotion(Request $request, Promotion $promotion)
    {
        $this->guardPromotion($promotion);
        $active = ! $promotion->isActive;
        $promotion->forceFill(['isActive' => $active])->save();
        if (! $active) {
            $this->clearPromotionProducts($promotion);
            $actor = $this->user();
            Approval::log($request, $promotion->tenantId, $actor->id, $actor->id, 'PROMO_EXPIRED', [
                'promoId' => $promotion->id,
            ]);
        }

        return back()->with('status', $active ? 'Promotion activated.' : 'Promotion turned off.');
    }

    public function destroyPromotion(Request $request, Promotion $promotion)
    {
        $this->guardPromotion($promotion);
        $this->clearPromotionProducts($promotion);
        $actor = $this->user();
        Approval::log($request, $promotion->tenantId, $actor->id, $actor->id, 'PROMO_EXPIRED', [
            'promoId' => $promotion->id,
            'deleted' => true,
        ]);
        $name = $promotion->name;
        $promotion->delete();

        return redirect()->route('promotions')->with('status', $name.' was deleted.');
    }

    private function validatedProduct(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:120'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'subCategory' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'categoryNew' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'originalPrice' => ['nullable', 'numeric', 'min:0'],
            'costPrice' => ['nullable', 'numeric', 'min:0'],
            'wholesalePrice' => ['nullable', 'numeric', 'min:0'],
            'vatRateOverride' => ['nullable', 'numeric', 'min:0'],
            'currentStock' => ['nullable', 'integer', 'min:0'],
            'minThreshold' => ['nullable', 'integer', 'min:0'],
            'reorderQty' => ['nullable', 'integer', 'min:0'],
            'leadTimeDays' => ['nullable', 'integer', 'min:0'],
            'availabilityMode' => ['required', 'in:BOTH,POS_ONLY,ONLINE_ONLY,DISABLED'],
            'supplierId' => ['nullable', 'string'],
            'expiresAt' => ['nullable', 'date'],
        ]);
        $data['supplierId'] = ($data['supplierId'] ?? '') !== '' ? $data['supplierId'] : null;
        $data['barcode'] = ($data['barcode'] ?? '') !== '' ? $data['barcode'] : null;
        $data['expiresAt'] = ($data['expiresAt'] ?? '') !== '' ? $data['expiresAt'] : null;
        $category = trim((string) ($data['category'] ?? ''));
        if ($category === '__new__') {
            $category = trim((string) ($data['categoryNew'] ?? ''));
            if ($category === '') {
                throw ValidationException::withMessages(['categoryNew' => 'Enter a category name.']);
            }
        }
        unset($data['categoryNew']);
        $data['category'] = $category !== '' ? $category : 'General';
        $data['costPrice'] = $data['costPrice'] ?? 0;
        $data['wholesalePrice'] = ($data['wholesalePrice'] ?? '') !== '' ? $data['wholesalePrice'] : null;
        $data['originalPrice'] = ($data['originalPrice'] ?? '') !== '' ? $data['originalPrice'] : $data['price'];
        $data['vatRateOverride'] = ($data['vatRateOverride'] ?? '') !== '' ? $data['vatRateOverride'] : null;

        return $data;
    }

    private function importStatus(int $saved, int $skipped, string $verb): string
    {
        if ($saved === 0) {
            return 'No products were '.$verb.'.';
        }

        $line = $saved.' '.($saved === 1 ? 'product' : 'products').' '.$verb.'.';

        return $skipped > 0 ? $line.' '.$skipped.' rows were skipped.' : $line;
    }

    private function amount(mixed $value): float
    {
        return (float) preg_replace('/[^0-9.]/', '', (string) $value);
    }

    private function saveMany(array $rows): array
    {
        $saved = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            try {
                $this->saveNewProduct($row);
                $saved++;
            } catch (ValidationException $exception) {
                $skipped++;
                $message = (string) collect($exception->errors())->flatten()->first();
                if (str_contains($message, 'product limit')) {
                    break;
                }
            }
        }

        return [$saved, $skipped];
    }

    private function saveNewProduct(array $data): Product
    {
        $tenantId = $this->tenantId();
        [$used, $limit] = $this->productAllowance();
        if ($used >= $limit) {
            throw ValidationException::withMessages(['name' => 'This shop has reached its '.$limit.' product limit.']);
        }

        $sku = trim((string) ($data['sku'] ?? ''));
        if ($sku === '') {
            $sku = $this->skuFromName((string) $data['name']);
        }
        $this->guardSku($tenantId, $sku);
        $barcode = ($data['barcode'] ?? null) ?: null;
        $this->guardBarcode($tenantId, $barcode);
        $stock = (int) ($data['currentStock'] ?? 0);

        return DB::transaction(function () use ($data, $tenantId, $sku, $barcode, $stock) {
            $product = Product::query()->create([
                'tenantId' => $tenantId,
                'name' => $data['name'],
                'sku' => $sku,
                'barcode' => $barcode,
                'category' => $data['category'] ?? 'General',
                'subCategory' => $data['subCategory'] ?? null,
                'description' => $data['description'] ?? null,
                'imageUrl' => $data['imageUrl'] ?? null,
                'price' => $data['price'] ?? 0,
                'originalPrice' => $data['originalPrice'] ?? ($data['price'] ?? 0),
                'costPrice' => $data['costPrice'] ?? 0,
                'wholesalePrice' => $data['wholesalePrice'] ?? null,
                'vatRateOverride' => $data['vatRateOverride'] ?? null,
                'currentStock' => $stock,
                'minThreshold' => $data['minThreshold'] ?? 5,
                'reorderQty' => $data['reorderQty'] ?? 0,
                'leadTimeDays' => $data['leadTimeDays'] ?? 0,
                'availabilityMode' => $data['availabilityMode'] ?? 'BOTH',
                'supplierId' => ($data['supplierId'] ?? null) ?: null,
                'expiresAt' => ($data['expiresAt'] ?? null) ?: null,
                'isActive' => true,
            ]);

            if ($stock > 0) {
                StockMovement::query()->create([
                    'productId' => $product->id,
                    'tenantId' => $tenantId,
                    'type' => 'ADJUSTMENT_IN',
                    'quantity' => $stock,
                    'beforeQty' => 0,
                    'afterQty' => $stock,
                    'notes' => 'Initial stock',
                    'createdAt' => now(),
                ]);
            }

            Approval::log(request(), $tenantId, $this->user()->id, $this->user()->id, 'PRODUCT_CREATED', [
                'productId' => $product->id,
                'sku' => $sku,
                'name' => $product->name,
            ]);

            return $product;
        });
    }

    private function productAllowance(): array
    {
        $tenantId = $this->tenantId();
        $used = Product::query()->where('tenantId', $tenantId)->count();
        $planId = Subscription::query()->where('tenantId', $tenantId)->value('planId');
        $limit = (int) ($planId ? (Plan::query()->whereKey($planId)->value('maxProducts') ?? 500) : 500);

        return [$used, max(1, $limit)];
    }

    private function customerData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);
        foreach (['phone', 'email', 'whatsapp', 'address', 'notes'] as $field) {
            $data[$field] = trim((string) ($data[$field] ?? '')) ?: null;
        }

        return $data;
    }

    private function guardCustomerPhone(string $tenantId, ?string $phone, ?string $ignoreId = null): void
    {
        if (! $phone) {
            return;
        }
        $query = Customer::query()->where('tenantId', $tenantId)->where('phone', $phone);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'A customer with this phone number already exists.',
            ]);
        }
    }

    private function managesCustomers(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageCustomers']);
    }

    private function managesProducts(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageProducts']);
    }

    private function seesCosts(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageFinancials']);
    }

    private function skuFromName(string $name, ?string $ignoreId = null): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $sku = count($parts) <= 1
            ? strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', $parts[0] ?? 'ITEM'), 0, 8))
            : collect($parts)->map(fn ($part) => strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', $part), 0, 3)))->filter()->implode('-');
        $base = $sku !== '' ? $sku : 'ITEM';
        $candidate = $base;
        $suffix = 1;
        while ($this->skuTaken($candidate, $ignoreId)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function skuTaken(string $sku, ?string $ignoreId): bool
    {
        $query = Product::query()->where('tenantId', $this->tenantId())->where('sku', $sku);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function storedImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }
        $request->validate([
            'image' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:500'],
        ], [
            'image.mimes' => 'The photo must be a JPG, PNG, or WebP under 500KB.',
            'image.max' => 'The photo must be a JPG, PNG, or WebP under 500KB.',
        ]);

        return $this->publicUrl($request->file('image')->store('products/'.$this->tenantId(), 'public'));
    }

    private function publicUrl(string $path): string
    {
        $disk = Storage::disk('public');
        if (! $disk instanceof FilesystemAdapter) {
            throw new \RuntimeException('Public disk is not available.');
        }

        return $disk->url($path);
    }

    private function guardSku(string $tenantId, string $sku, ?string $ignoreId = null): void
    {
        $query = Product::query()->where('tenantId', $tenantId)->where('sku', $sku);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['sku' => 'That SKU is already in use.']);
        }
    }

    private function guardBarcode(string $tenantId, ?string $barcode, ?string $ignoreProductId = null): void
    {
        if (! $barcode) {
            return;
        }
        $products = Product::query()->where('tenantId', $tenantId)->where('barcode', $barcode);
        if ($ignoreProductId) {
            $products->where('id', '!=', $ignoreProductId);
        }
        $variants = ProductVariant::query()->where('tenantId', $tenantId)->where('barcode', $barcode);
        if ($products->exists() || $variants->exists()) {
            throw ValidationException::withMessages(['barcode' => 'That barcode is already in use.']);
        }
    }

    private function salesWindow(Request $request): array
    {
        $period = (string) $request->query('period', 'today');
        $from = $request->query('from');
        $to = $request->query('to');

        [$start, $end, $label] = match ($period) {
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), 'Yesterday'],
            '7' => [now()->subDays(6)->startOfDay(), now()->endOfDay(), 'Last 7 days'],
            '30' => [now()->subDays(29)->startOfDay(), now()->endOfDay(), 'Last 30 days'],
            'custom' => [
                $from ? \Illuminate\Support\Carbon::parse($from)->startOfDay() : now()->startOfDay(),
                ($to ?: $from) ? \Illuminate\Support\Carbon::parse($to ?: $from)->endOfDay() : now()->endOfDay(),
                'Custom',
            ],
            default => [now()->startOfDay(), now()->endOfDay(), 'Today'],
        };

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        if ($period === 'custom') {
            $label = $start->isSameDay($end)
                ? $start->format('d M Y')
                : $start->format('d M').' – '.$end->format('d M Y');
        }

        return [$start, $end, $period === 'custom' ? 'custom' : ($period === 'yesterday' || $period === '7' || $period === '30' ? $period : 'today'), $label];
    }

    private function hourChart($sales)
    {
        $byHour = $sales->groupBy(fn ($order) => (int) $order->createdAt?->format('G'));
        $peak = max(1, (float) ($byHour->map(fn ($hour) => (float) $hour->sum('netAmount'))->max() ?? 0));

        return collect(range(8, 23))->map(function (int $hour) use ($byHour, $peak) {
            $amount = (float) ($byHour->get($hour)?->sum('netAmount') ?? 0);

            return [
                'label' => $hour < 12 ? $hour.'am' : ($hour === 12 ? '12pm' : ($hour - 12).'pm'),
                'value' => $this->short($amount, true),
                'height' => round(($amount / $peak) * 100, 1),
            ];
        });
    }

    private function topProducts($orderIds)
    {
        if ($orderIds->isEmpty()) {
            return collect();
        }

        $names = Product::query()->where('tenantId', $this->tenantId())->pluck('name', 'id');

        return OrderItem::query()
            ->whereIn('orderId', $orderIds)
            ->get()
            ->groupBy('productId')
            ->map(fn ($items, $productId) => [
                'name' => $names[$productId] ?? 'Product',
                'qty' => (int) $items->sum('quantity'),
                'total' => (float) $items->sum('lineTotal'),
            ])
            ->sortByDesc('qty')
            ->take(5)
            ->values();
    }

    private function finishPending(Order $order, SaleRecorder $sales, string $gatewayRef): void
    {
        $sales->completePending($order, $gatewayRef);
        DB::transaction(function () use ($order) {
            foreach (OrderItem::query()->where('orderId', $order->id)->get() as $item) {
                $product = Product::query()->where('tenantId', $order->tenantId)->lockForUpdate()->find($item->productId);
                if (! $product) {
                    continue;
                }
                $product->forceFill([
                    'reservedQty' => max(0, (int) $product->reservedQty - (int) $item->quantity),
                ])->save();
            }
            Hold::query()->where('orderId', $order->id)->where('status', 'ACTIVE')->update([
                'status' => 'RESTORED',
                'restoredAt' => now(),
            ]);
        });
    }

    private function applyOrderStock(Order $order, string $next): void
    {
        $paid = Transaction::query()->where('orderId', $order->id)->where('status', 'SUCCESS')->exists();
        foreach (OrderItem::query()->where('orderId', $order->id)->get() as $item) {
            $product = Product::query()->where('tenantId', $order->tenantId)->lockForUpdate()->find($item->productId);
            if (! $product) {
                continue;
            }
            $qty = (int) $item->quantity;
            $before = (int) $product->currentStock;
            $reserved = (int) $product->reservedQty;

            if ($order->status === 'PENDING' && $next === 'RESERVED') {
                if (! $paid) {
                    $product->forceFill(['currentStock' => max(0, $before - $qty)])->save();
                }
                continue;
            }

            if ($order->status === 'RESERVED' && $next === 'COMPLETED') {
                $product->forceFill(['reservedQty' => max(0, $reserved - $qty)])->save();
                $this->stockMove($product, $order, 'SALE', -$qty, $before + $qty, $before);
                continue;
            }

            if ($order->status === 'PENDING' && $next === 'CANCELLED') {
                $product->forceFill(['reservedQty' => max(0, $reserved - $qty)])->save();
                continue;
            }

            if ($order->status === 'RESERVED' && $next === 'CANCELLED') {
                $product->forceFill([
                    'currentStock' => $before + $qty,
                    'reservedQty' => max(0, $reserved - $qty),
                ])->save();
                $this->stockMove($product, $order, 'RETURN', $qty, $before, $before + $qty);
                continue;
            }

            if ($order->status === 'COMPLETED' && $next === 'RETURNED') {
                $product->forceFill(['currentStock' => $before + $qty])->save();
                $this->stockMove($product, $order, 'RETURN', $qty, $before, $before + $qty);
            }
        }
    }

    private function stockMove(Product $product, Order $order, string $type, int $quantity, int $before, int $after): void
    {
        StockMovement::query()->create([
            'productId' => $product->id,
            'tenantId' => $order->tenantId,
            'type' => $type,
            'quantity' => $quantity,
            'beforeQty' => $before,
            'afterQty' => $after,
            'reference' => $order->id,
            'createdAt' => now(),
        ]);
    }

    /** @return list<string> */
    private function orderTransitions(string $status): array
    {
        return match ($status) {
            'PENDING' => ['RESERVED', 'COMPLETED', 'CANCELLED'],
            'RESERVED' => ['COMPLETED', 'CANCELLED'],
            'COMPLETED' => ['RETURNED'],
            default => [],
        };
    }

    private function orderNotice(Request $request, Order $order, string $action, string $title, string $message, array $details): void
    {
        $actor = $this->user();
        Approval::log($request, $order->tenantId, $actor->id, $actor->id, $action, ['orderId' => $order->id] + $details);
        Notification::query()->create([
            'tenantId' => $order->tenantId,
            'type' => 'AUDIT_ALERT',
            'title' => $title,
            'message' => $message,
            'entityId' => $order->id,
            'createdAt' => now(),
        ]);
    }

    private function managesOrders(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageOrders']);
    }

    private function orderQuery()
    {
        $query = Order::query()->where('tenantId', $this->tenantId());
        if ($this->cashierOnly()) {
            $query->where('cashierId', $this->user()->id);
        }

        return $query;
    }

    private function resumeHoldId(Request $request): ?string
    {
        $id = $request->input('resume_hold');
        if (! $id) {
            return null;
        }

        $hold = Hold::query()->where('tenantId', $this->tenantId())->whereKey($id)->where('status', 'ACTIVE')->first();
        if (! $hold || ($this->cashierOnly() && $hold->cashierId !== $this->user()->id)) {
            return null;
        }

        return $hold->id;
    }

    private function guardHold(Hold $hold): void
    {
        abort_unless($hold->tenantId === $this->tenantId() && $hold->status === 'ACTIVE', 404);
        if ($this->cashierOnly()) {
            abort_unless($hold->cashierId === $this->user()->id, 404);
        }
    }

    private function guardOrder(Order $order): void
    {
        abort_unless($order->tenantId === $this->tenantId(), 404);
        if ($this->cashierOnly()) {
            abort_unless($order->cashierId === $this->user()->id, 404);
        }
    }

    private function managesRefunds(): bool
    {
        if ($this->cashierOnly()) {
            return false;
        }
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canProcessRefunds']);
    }

    private function canRequestRefund(Order $order): bool
    {
        if ($order->tenantId !== $this->tenantId() || $order->status !== 'COMPLETED') {
            return false;
        }
        if ($this->cashierOnly()) {
            return $this->managesOrders() && $order->cashierId === $this->user()->id;
        }

        return $this->managesRefunds();
    }

    private function refundableOrders(): array
    {
        $tenantId = $this->tenantId();
        $taken = $this->refundsTaken($tenantId);
        $options = ['' => 'Choose a sale'];
        $caps = [];
        $sales = Order::query()
            ->where('tenantId', $tenantId)
            ->where('status', 'COMPLETED')
            ->latest('createdAt')
            ->get(['id', 'paymentRef', 'customerName', 'netAmount', 'totalAmount']);

        foreach ($sales as $sale) {
            $left = $this->refundableLeft($sale, (float) ($taken[$sale->id] ?? 0));
            if ($left < 0.01) {
                continue;
            }
            $ref = strtoupper(substr((string) ($sale->paymentRef ?: $sale->id), -8));
            $options[$sale->id] = $ref.' · '.($sale->customerName ?: 'Walk-in').' · ₦'.number_format($left, 2);
            $caps[$sale->id] = number_format($left, 2, '.', '');
        }

        return ['options' => $options, 'caps' => $caps];
    }

    private function guardRefundAmount(Order $order, float $amount): void
    {
        if ($order->status !== 'COMPLETED') {
            throw ValidationException::withMessages([
                'orderId' => 'Only a completed sale can be refunded.',
            ]);
        }
        $left = $this->refundableLeft($order, (float) ($this->refundsTaken($order->tenantId)[$order->id] ?? 0));
        if ($left < 0.01) {
            throw ValidationException::withMessages([
                'orderId' => 'This sale has already been fully refunded.',
            ]);
        }
        if (round($amount, 2) > $left + 0.001) {
            throw ValidationException::withMessages([
                'amount' => 'That is more than the ₦'.number_format($left, 2).' still refundable on this sale.',
            ]);
        }
    }

    private function refundableLeft(Order $order, float $taken): float
    {
        $net = round((float) ($order->netAmount ?? $order->totalAmount), 2);

        return round($net - $taken, 2);
    }

    private function refundsTaken(string $tenantId)
    {
        return Refund::query()
            ->where('tenantId', $tenantId)
            ->whereIn('status', ['PENDING', 'APPROVED', 'PROCESSED'])
            ->get(['orderId', 'amount'])
            ->groupBy('orderId')
            ->map(fn ($rows) => round($rows->sum(fn ($row) => (float) $row->amount), 2));
    }

    private function decideRefund(Request $request, Refund $refund, string $status, string $message)
    {
        abort_unless($this->managesRefunds() && Approval::canAuthorize($this->user()), 403);
        abort_unless($refund->tenantId === $this->tenantId(), 404);
        $expected = $status === 'PROCESSED' ? 'APPROVED' : 'PENDING';
        if ($refund->status !== $expected) {
            return back()->withErrors(['refund' => 'That refund can no longer be updated.']);
        }

        $actor = $this->user();
        $fill = ['status' => $status, 'approvedBy' => $actor->id];
        if ($status === 'PROCESSED') {
            $fill['processedAt'] = now();
        }
        $refund->forceFill($fill)->save();
        Approval::log($request, $this->tenantId(), $actor->id, $actor->id, 'REFUND_ISSUED', [
            'refundId' => $refund->id,
            'orderId' => $refund->orderId,
            'amount' => (float) $refund->amount,
            'reason' => $refund->reason,
            'status' => $status,
        ]);

        return back()->with('status', $message);
    }

    private function supplierData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:50'],
            'contactWhatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'bankName' => ['nullable', 'string', 'max:120'],
            'bankAccount' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'form' => ['nullable', 'string'],
        ]);
        unset($data['form']);
        foreach (['contactEmail', 'contactPhone', 'contactWhatsapp', 'address', 'bankName', 'bankAccount', 'notes'] as $field) {
            $data[$field] = trim((string) ($data[$field] ?? '')) ?: null;
        }

        return $data;
    }

    private function promotionData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'discountType' => ['required', 'in:PERCENTAGE,FIXED'],
            'discountValue' => ['required', 'numeric', 'gt:0'],
            'productId' => ['nullable', 'string'],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'form' => ['nullable', 'string'],
        ]);
        if ($data['discountType'] === 'PERCENTAGE' && (float) $data['discountValue'] > 100) {
            throw ValidationException::withMessages([
                'discountValue' => 'A percentage cannot be more than 100.',
            ]);
        }
        $productId = trim((string) ($data['productId'] ?? '')) ?: null;
        if ($productId && ! Product::query()->where('tenantId', $this->tenantId())->whereKey($productId)->exists()) {
            throw ValidationException::withMessages([
                'productId' => 'Choose a product from this shop.',
            ]);
        }
        $start = \Illuminate\Support\Carbon::parse($data['startDate'])->startOfDay();
        $end = \Illuminate\Support\Carbon::parse($data['endDate'])->endOfDay();

        return [
            'name' => $data['name'],
            'discountType' => $data['discountType'],
            'discountValue' => $data['discountValue'],
            'productId' => $productId,
            'startDatetime' => $start,
            'endDatetime' => $end,
            'windowOpen' => now()->between($start, $end),
        ];
    }

    private function managesPromotions(): bool
    {
        if ($this->cashierOnly()) {
            return false;
        }
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManagePromotions']);
    }

    private function guardPromotion(Promotion $promotion): void
    {
        abort_unless($this->managesPromotions(), 403);
        abort_unless($promotion->tenantId === $this->tenantId(), 404);
    }

    private function clearPromotionProducts(Promotion $promotion): void
    {
        Product::query()->where('tenantId', $promotion->tenantId)->where('activePromoId', $promotion->id)->update([
            'activePromoId' => null,
            'isDiscounted' => false,
        ]);
    }

    private function managesSuppliers(): bool
    {
        if ($this->cashierOnly()) {
            return false;
        }
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageSuppliers']);
    }

    private function managesInventory(): bool
    {
        if ($this->cashierOnly()) {
            return false;
        }
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageInventory']);
    }

    private function guardPurchaseOrder(PurchaseOrder $purchaseOrder): void
    {
        abort_unless($this->managesInventory(), 403);
        abort_unless($purchaseOrder->tenantId === $this->tenantId(), 404);
    }

    private function cashierOnly(): bool
    {
        $user = $this->user()->loadMissing('role');
        $role = $user->isSuperAdmin ? 'SUPER_ADMIN' : ($user->role?->role ?? 'CASHIER');

        return $role === 'CASHIER';
    }
}
