<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Merchant\MerchantController;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\AwayCheckIn;
use App\Models\BillingInvoice;
use App\Models\CatalogueProduct;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\PasswordResetToken;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesLedger;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\TaxFiling;
use App\Models\Tenant;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\TenantEraser;
use App\Support\AuthMail;
use App\Support\CatalogueCategories;
use App\Support\CatalogueCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlatformController extends MerchantController
{
    public function home(Request $request)
    {
        [$period, $from, $to, $periodLabel, $chart, $wide] = $this->chartWindow($request);

        return view('platform.home', [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'periodLabel' => $periodLabel,
            'chart' => $chart,
            'wide' => $wide,
            'stats' => [
                ['label' => 'Total shops', 'value' => Tenant::query()->count()],
                ['label' => 'Active shops', 'value' => Tenant::query()->where('isActive', true)->where('approvalStatus', 'APPROVED')->count()],
                ['label' => 'Pending approvals', 'value' => Tenant::query()->where('approvalStatus', 'PENDING')->count()],
                ['label' => 'Active subscriptions', 'value' => Subscription::query()->where('status', 'ACTIVE')->count()],
                ['label' => 'Suspended', 'value' => Subscription::query()->where('status', 'SUSPENDED')->count()],
                ['label' => 'Total revenue', 'value' => $this->money(BillingInvoice::query()->where('status', 'PAID')->sum('amount'))],
            ],
        ]);
    }

    private function chartWindow(Request $request): array
    {
        $period = (string) $request->query('period', '30');
        if (! in_array($period, ['today', 'yesterday', '7', '30', 'custom'], true)) {
            $period = '30';
        }
        $now = Carbon::now('Africa/Lagos');
        if ($period === 'today') {
            $start = $now->copy()->startOfDay();
            $end = $now->copy()->endOfDay();
            $label = 'Today';
        } elseif ($period === 'yesterday') {
            $start = $now->copy()->subDay()->startOfDay();
            $end = $start->copy()->endOfDay();
            $label = 'Yesterday';
        } elseif ($period === '7') {
            $start = $now->copy()->subDays(6)->startOfDay();
            $end = $now->copy()->endOfDay();
            $label = 'Last 7 days';
        } elseif ($period === 'custom') {
            $start = $this->lagosDay((string) $request->query('from', '')) ?? $now->copy()->startOfDay();
            $end = ($this->lagosDay((string) $request->query('to', '')) ?? $start->copy())->endOfDay();
            if ($end->lt($start)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }
            if ((int) $start->diffInDays($end) > 92) {
                $end = $start->copy()->addDays(92)->endOfDay();
            }
            $label = $start->isSameDay($end)
                ? $start->format('d M Y')
                : $start->format('d M').' – '.$end->format('d M Y');
        } else {
            $period = '30';
            $start = $now->copy()->subDays(29)->startOfDay();
            $end = $now->copy()->endOfDay();
            $label = 'Last 30 days';
        }
        $chart = $start->isSameDay($end) ? $this->hourBars($start) : $this->dayBars($start, $end);

        return [$period, $start->toDateString(), $end->toDateString(), $label, $chart, count($chart) > 16];
    }

    private function lagosDay(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value, 'Africa/Lagos')->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function dayBars(Carbon $start, Carbon $end): array
    {
        $rows = SalesLedger::query()
            ->whereBetween('createdAt', [$start->copy()->utc(), $end->copy()->utc()])
            ->selectRaw('(("createdAt" AT TIME ZONE \'UTC\') AT TIME ZONE \'Africa/Lagos\')::date as bucket, sum("grossRevenue") as total')
            ->groupByRaw('(("createdAt" AT TIME ZONE \'UTC\') AT TIME ZONE \'Africa/Lagos\')::date')
            ->pluck('total', 'bucket');
        $totals = [];
        foreach ($rows as $day => $total) {
            $totals[substr((string) $day, 0, 10)] = (float) $total;
        }
        $peak = max(1.0, $totals === [] ? 0 : max($totals));
        $chart = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();
        $i = 0;
        while ($cursor->lte($last)) {
            $amount = $totals[$cursor->toDateString()] ?? 0;
            $chart[] = [
                'label' => $i === 0 || $cursor->day === 1 ? $cursor->format('j M') : (string) $cursor->day,
                'title' => $cursor->format('d M Y').' · '.$this->short($amount, true),
                'height' => round(($amount / $peak) * 100, 1),
            ];
            $cursor->addDay();
            $i++;
        }

        return $chart;
    }

    private function hourBars(Carbon $day): array
    {
        $rows = SalesLedger::query()
            ->whereBetween('createdAt', [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()])
            ->selectRaw('extract(hour from (("createdAt" AT TIME ZONE \'UTC\') AT TIME ZONE \'Africa/Lagos\'))::int as bucket, sum("grossRevenue") as total')
            ->groupByRaw('extract(hour from (("createdAt" AT TIME ZONE \'UTC\') AT TIME ZONE \'Africa/Lagos\'))::int')
            ->pluck('total', 'bucket');
        $totals = [];
        foreach ($rows as $hour => $total) {
            $totals[(int) $hour] = (float) $total;
        }
        $peak = max(1.0, $totals === [] ? 0 : max($totals));
        $chart = [];
        foreach (range(8, 23) as $hour) {
            $amount = $totals[$hour] ?? 0;
            $chart[] = [
                'label' => $hour < 12 ? $hour.'am' : ($hour === 12 ? '12pm' : ($hour - 12).'pm'),
                'title' => ($hour < 12 ? $hour.'am' : ($hour === 12 ? '12pm' : ($hour - 12).'pm')).' · '.$this->short($amount, true),
                'height' => round(($amount / $peak) * 100, 1),
            ];
        }

        return $chart;
    }

    public function tenants(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $approval = (string) $request->query('approval', '');
        if (! in_array($approval, ['APPROVED', 'PENDING', 'REJECTED'], true)) {
            $approval = '';
        }
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['active', 'inactive', 'deleted'], true)) {
            $status = '';
        }
        $query = $status === 'deleted'
            ? Tenant::onlyTrashed()->latest('deletedAt')
            : Tenant::query()->latest('createdAt');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($row) use ($like) {
                $row->where('name', 'ilike', $like)
                    ->orWhere('slug', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like);
            });
        }
        if ($approval !== '') {
            $query->where('approvalStatus', $approval);
        }
        if ($status === 'active') {
            $query->where('isActive', true);
        }
        if ($status === 'inactive') {
            $query->where('isActive', false);
        }
        $perPage = 20;
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('platform.tenants', [
            'tenants' => $query->forPage($page, $perPage)->get(),
            'q' => $q,
            'approval' => $approval,
            'status' => $status,
            'filtered' => $q !== '' || $approval !== '' || $status !== '',
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'ownTenantId' => $this->user()->tenantId,
        ]);
    }

    public function tenant(Tenant $tenant)
    {
        $tenant->load([
            'subscription.plan',
            'branches' => fn ($query) => $query->orderByDesc('isMain')->orderBy('name'),
        ]);
        $tenant->loadCount([
            'users as usersCount',
            'orders as ordersCount',
            'products as productsCount',
        ]);

        return view('platform.tenant', [
            'tenant' => $tenant,
            'people' => $tenant->users()->with('role')->orderBy('name')->get(),
            'own' => $tenant->id === $this->user()->tenantId,
            'me' => $this->user()->id,
            'plans' => Plan::query()->where('isActive', true)->orderBy('monthlyPrice')->get(),
            'latestCheckIn' => AwayCheckIn::query()->with('user')->where('tenantId', $tenant->id)->latest('createdAt')->first(),
            'deletedByName' => $tenant->deletedBy ? User::query()->whereKey($tenant->deletedBy)->value('name') : null,
            'suppliers' => Supplier::query()
                ->where('tenantId', $tenant->id)
                ->select('suppliers.*')
                ->selectSub(
                    Product::query()->selectRaw('count(*)')->whereColumn('products.supplierId', 'suppliers.id')->where('products.isActive', true),
                    'productsCount'
                )
                ->selectSub(
                    Product::query()->selectRaw('count(*)')->whereColumn('products.supplierId', 'suppliers.id')->where('products.isActive', true)->whereColumn('products.currentStock', '<=', 'products.minThreshold'),
                    'lowCount'
                )
                ->selectSub(
                    PurchaseOrder::query()->selectRaw('count(*)')->whereColumn('purchase_orders.supplierId', 'suppliers.id')->where('purchase_orders.status', 'DRAFT'),
                    'draftsCount'
                )
                ->orderByDesc('isActive')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function updateTenant(Request $request, Tenant $tenant)
    {
        $action = (string) $request->input('action');
        if (! in_array($action, ['approve', 'reject', 'suspend', 'activate'], true)) {
            return back()->withErrors(['tenant' => 'That action is not available.']);
        }
        if ($action === 'suspend' && $tenant->id === $this->user()->tenantId) {
            return back()->withErrors(['tenant' => 'This is your own shop.']);
        }

        if ($action === 'suspend') {
            $tenant->isActive = false;
        }
        if ($action === 'activate') {
            $tenant->isActive = true;
        }
        if ($action === 'approve') {
            $tenant->approvalStatus = 'APPROVED';
            $tenant->approvedAt = Carbon::now();
            $tenant->approvedBy = $this->user()->id;
        }
        if ($action === 'reject') {
            $tenant->approvalStatus = 'REJECTED';
        }
        $tenant->save();

        $message = match ($action) {
            'approve' => 'Shop approved.',
            'reject' => 'Shop rejected.',
            'suspend' => 'Shop suspended.',
            default => 'Shop activated.',
        };

        return back()->with('status', $message);
    }

    public function overrideSubscription(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'string', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:MONTHLY,QUARTERLY,ANNUALLY'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $plan = Plan::query()->findOrFail($data['plan_id']);
        $cycle = $data['billing_cycle'];
        $admin = $this->user();
        $now = now();

        $periodEnd = match ($cycle) {
            'QUARTERLY' => $now->copy()->addMonths(3),
            'ANNUALLY' => $now->copy()->addYear(),
            default => $now->copy()->addMonth(),
        };

        $previous = Subscription::query()->where('tenantId', $tenant->id)->first();
        $previousPlanName = $previous?->plan?->name ?? 'none';

        Subscription::query()->updateOrCreate(
            ['tenantId' => $tenant->id],
            [
                'planId' => $plan->id,
                'status' => 'ACTIVE',
                'billingCycle' => $cycle,
                'currentPeriodStart' => $now,
                'currentPeriodEnd' => $periodEnd,
                'gracePeriodDays' => 7,
                'gracePeriodEndsAt' => $periodEnd->copy()->addDays(7),
                'lastRenewedAt' => $now,
                'nextBillingDate' => $periodEnd,
                'cancelAtPeriodEnd' => false,
                'cancelledAt' => null,
                'cancellationReason' => null,
            ]
        );

        // Audit log
        AuditLog::query()->create([
            'tenantId' => $tenant->id,
            'userId' => $admin->id,
            'action' => 'SETTINGS_UPDATED',
            'details' => [
                'type' => 'PLAN_OVERRIDE',
                'fromPlan' => $previousPlanName,
                'toPlan' => $plan->name,
                'billingCycle' => $cycle,
                'reason' => $data['reason'],
                'adminId' => $admin->id,
                'adminName' => $admin->name,
            ],
            'ipAddress' => $request->ip(),
            'timestamp' => $now,
        ]);

        // In-app notification to merchant
        Notification::query()->create([
            'tenantId' => $tenant->id,
            'type' => 'SYSTEM',
            'title' => 'Plan updated by SalesDock',
            'message' => 'Your plan has been changed to '.$plan->name.' ('.$cycle.') by the SalesDock team.',
            'isRead' => false,
            'createdAt' => $now,
        ]);

        // Email the tenant owner
        $owner = User::query()
            ->where('tenantId', $tenant->id)
            ->whereHas('role', fn ($q) => $q->where('name', 'Owner'))
            ->first();

        if ($owner?->email) {
            AuthMail::send($owner->email, $owner->name, 'Your SalesDock plan has been updated', [
                'preheader' => 'Your plan has been changed to '.$plan->name.'.',
                'heading' => 'Plan updated',
                'kicker' => $tenant->name,
                'paragraphs' => [
                    'Hi <strong style="color:#111827;">'.e($owner->name).'</strong>, your SalesDock plan has been updated by our team.',
                    'Your account for <strong>'.e($tenant->name).'</strong> is now on the <strong>'.e($plan->name).'</strong> plan ('.strtolower($cycle).' billing), effective immediately.',
                    'Your new period ends on <strong>'.$periodEnd->timezone('Africa/Lagos')->format('d M Y').'</strong>.',
                ],
                'url' => route('billing'),
                'label' => 'View billing',
                'note' => 'If you have questions about this change, please contact our support team.',
            ]);
        }

        return back()->with('status', 'Plan overridden to '.$plan->name.' for '.$tenant->name.'.');
    }

    public function destroyTenant(Request $request, Tenant $tenant, TenantEraser $eraser)
    {
        if ($tenant->id === $this->user()->tenantId) {
            return back()->withErrors(['tenant' => 'This is your own shop.']);
        }
        if ($tenant->trashed()) {
            return back()->withErrors(['tenant' => 'This shop is already deleted.']);
        }

        $eraser->close($tenant, $this->user()->id);

        $message = $tenant->name.' deleted. You can restore it until '.$tenant->purgesAt()->timezone('Africa/Lagos')->format('d M Y').'.';
        if ($request->input('from') === 'list') {
            return back()->with('status', $message);
        }

        return redirect()->route('admin.tenants.show', $tenant)->with('status', $message);
    }

    public function restoreTenant(Tenant $tenant, TenantEraser $eraser)
    {
        if (! $tenant->trashed()) {
            return back()->withErrors(['tenant' => 'This shop is not deleted.']);
        }

        $eraser->restore($tenant);

        return back()->with('status', 'Shop restored. Everyone can sign in again.');
    }

    public function eraseTenant(Tenant $tenant, TenantEraser $eraser)
    {
        if ($tenant->id === $this->user()->tenantId) {
            return back()->withErrors(['tenant' => 'This is your own shop.']);
        }
        if (! $tenant->trashed()) {
            return back()->withErrors(['tenant' => 'Delete the shop first. It can then be erased for good.']);
        }

        try {
            $eraser->erase($tenant);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['tenant' => 'This shop could not be erased.']);
        }

        return redirect()->route('admin.tenants', ['status' => 'deleted'])->with('status', 'Shop and all its data erased.');
    }

    public function storeSupplier(Request $request, Tenant $tenant)
    {
        $supplier = Supplier::query()->create($this->supplierData($request) + [
            'tenantId' => $tenant->id,
            'isActive' => true,
        ]);
        $this->logSupplierChange($request, $tenant, $supplier, 'SUPPLIER_ADDED');

        return back()->with('status', $supplier->name.' was added.');
    }

    public function updateSupplier(Request $request, Tenant $tenant, Supplier $supplier)
    {
        abort_unless($supplier->tenantId === $tenant->id, 404);
        $supplier->update($this->supplierData($request));
        $this->logSupplierChange($request, $tenant, $supplier, 'SUPPLIER_UPDATED');

        return back()->with('status', $supplier->name.' was updated.');
    }

    public function destroySupplier(Request $request, Tenant $tenant, Supplier $supplier)
    {
        abort_unless($supplier->tenantId === $tenant->id, 404);
        $supplier->forceFill(['isActive' => false])->save();
        $this->logSupplierChange($request, $tenant, $supplier, 'SUPPLIER_DEACTIVATED');

        return back()->with('status', $supplier->name.' was removed. You can restore it from this page.');
    }

    public function restoreSupplier(Request $request, Tenant $tenant, Supplier $supplier)
    {
        abort_unless($supplier->tenantId === $tenant->id, 404);
        $supplier->forceFill(['isActive' => true])->save();
        $this->logSupplierChange($request, $tenant, $supplier, 'SUPPLIER_RESTORED');

        return back()->with('status', $supplier->name.' was restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:50'],
            'contactWhatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'bankName' => ['nullable', 'string', 'max:120'],
            'bankAccount' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:255'],
            'restockDrafts' => ['nullable', 'boolean'],
            'form' => ['nullable', 'string'],
            'supplier' => ['nullable', 'string'],
        ]);
        unset($data['form'], $data['supplier']);
        $data['name'] = trim($data['name']);
        foreach (['contactEmail', 'contactPhone', 'contactWhatsapp', 'address', 'bankName', 'bankAccount', 'notes'] as $field) {
            $data[$field] = trim((string) ($data[$field] ?? '')) ?: null;
        }
        $data['restockDrafts'] = $request->boolean('restockDrafts');

        return $data;
    }

    private function logSupplierChange(Request $request, Tenant $tenant, Supplier $supplier, string $type): void
    {
        $admin = $this->user();
        AuditLog::query()->create([
            'tenantId' => $tenant->id,
            'userId' => $admin->id,
            'action' => 'SETTINGS_UPDATED',
            'details' => [
                'type' => $type,
                'supplierId' => $supplier->id,
                'supplierName' => $supplier->name,
                'adminId' => $admin->id,
                'adminName' => $admin->name,
            ],
            'ipAddress' => $request->ip(),
            'timestamp' => now(),
        ]);
    }

    public function destroyUser(Tenant $tenant, User $user)
    {
        if ($user->tenantId !== $tenant->id) {
            abort(404);
        }
        if ($user->id === $this->user()->id) {
            return back()->withErrors(['tenant' => 'You cannot delete your own account.']);
        }

        try {
            DB::transaction(function () use ($user) {
                DB::table('staff_payroll')->where('userId', $user->id)->delete();
                $email = $user->email;
                $user->delete();
                if ($email) {
                    TenantRegistration::query()->where('email', $email)->delete();
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['tenant' => 'This person could not be deleted.']);
        }

        return back()->with('status', 'Person deleted.');
    }

    public function subscriptions(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['ACTIVE', 'TRIALING', 'PAST_DUE', 'SUSPENDED', 'CANCELLED', 'EXPIRED'], true)) {
            $status = '';
        }
        $query = Subscription::query()->with(['tenant', 'plan'])->latest('createdAt');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->whereHas('tenant', function ($row) use ($like) {
                $row->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like);
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        [$rows, $total, $page, $pages] = $this->pageOf($query, $request);

        return view('platform.subscriptions', [
            'subscriptions' => $rows,
            'q' => $q,
            'status' => $status,
            'filtered' => $q !== '' || $status !== '',
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function billing(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['PAID', 'PENDING', 'FAILED', 'REFUNDED', 'WAIVED'], true)) {
            $status = '';
        }
        $cycle = (string) $request->query('cycle', '');
        if (! in_array($cycle, ['MONTHLY', 'QUARTERLY', 'ANNUALLY'], true)) {
            $cycle = '';
        }
        $query = BillingInvoice::query()->with('tenant')->latest('createdAt');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->whereHas('tenant', fn ($row) => $row->where('name', 'ilike', $like));
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($cycle !== '') {
            $query->where('billingCycle', $cycle);
        }
        [$rows, $total, $page, $pages] = $this->pageOf($query, $request);
        $sum = fn (string $state) => BillingInvoice::query()->where('status', $state)->sum('amount');

        return view('platform.billing', [
            'invoices' => $rows,
            'q' => $q,
            'status' => $status,
            'cycle' => $cycle,
            'filtered' => $q !== '' || $status !== '' || $cycle !== '',
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'cards' => [
                ['label' => 'Collected', 'value' => $this->money($sum('PAID'))],
                ['label' => 'Pending', 'value' => $this->money($sum('PENDING'))],
                ['label' => 'Failed', 'value' => $this->money($sum('FAILED'))],
                ['label' => 'Refunded', 'value' => $this->money($sum('REFUNDED'))],
            ],
            'plans' => Plan::query()->orderBy('monthlyPrice')->get(),
            'catalogue' => $this->planFeatures(),
        ]);
    }

    public function plans()
    {
        return redirect()->route('admin.billing')->withFragment('plans');
    }

    public function storePlan(Request $request)
    {
        $data = $this->planInput($request, null);
        Plan::query()->create($data + ['isActive' => true]);

        return redirect()->route('admin.billing')->withFragment('plans')->with('status', 'Plan added.');
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        $plan->fill($this->planInput($request, $plan))->save();

        return redirect()->route('admin.billing')->withFragment('plans')->with('status', 'Plan saved.');
    }

    public function setPlanActive(Request $request, Plan $plan)
    {
        $plan->isActive = $request->boolean('active');
        $plan->save();

        return back()->with('status', $plan->isActive ? 'Plan turned on.' : 'Plan turned off.');
    }

    private function pageOf($query, Request $request): array
    {
        $perPage = 20;
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return [$query->forPage($page, $perPage)->get(), $total, $page, $pages];
    }

    private function planInput(Request $request, ?Plan $plan): array
    {
        $known = $plan
            ? array_values(array_unique([...$this->planFeatures(), ...$this->featureList($plan)]))
            : $this->planFeatures();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('plans', 'name')->ignore($plan?->id)],
            'tier' => ['required', Rule::in(['STARTER', 'PROFESSIONAL', 'ENTERPRISE'])],
            'description' => ['nullable', 'string', 'max:500'],
            'monthlyPrice' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'quarterlyPrice' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'annualPrice' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'maxBranches' => ['required', 'integer', 'min:0', 'max:999999'],
            'maxUsers' => ['required', 'integer', 'min:0', 'max:999999'],
            'maxProducts' => ['required', 'integer', 'min:0', 'max:999999'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', Rule::in($known)],
        ], [
            'name.unique' => 'A plan named '.$request->input('name').' already exists.',
        ]);
        $picked = array_values(array_intersect($data['features'] ?? [], $known));
        $description = trim((string) ($data['description'] ?? ''));

        return [
            'name' => trim($data['name']),
            'tier' => $data['tier'],
            'description' => $description !== '' ? $description : null,
            'monthlyPrice' => $data['monthlyPrice'],
            'quarterlyPrice' => $data['quarterlyPrice'],
            'annualPrice' => $data['annualPrice'],
            'maxBranches' => $data['maxBranches'],
            'maxUsers' => $data['maxUsers'],
            'maxProducts' => $data['maxProducts'],
            'features' => $picked,
        ];
    }

    private function featureList(Plan $plan): array
    {
        $raw = $plan->features;

        return is_array($raw) ? array_values(array_filter($raw, fn ($item) => is_string($item) && trim($item) !== '')) : [];
    }

    private function planFeatures(): array
    {
        return [
            'POS Terminal',
            'Basic Inventory',
            'Advanced Inventory',
            'Order Management',
            'Customer Records',
            'Multi-branch Support',
            'Analytics Dashboard',
            'Advanced Analytics',
            'Payroll Management',
            'Promotions & Discounts',
            'Supplier Management',
            'Tax Filings',
            'Audit Logs',
            'Activity Logs',
            'Storefront / Online Store',
            'Purchase Orders',
            'Refund Management',
            'Role Management',
            'Staff Management',
            'Loyalty Program',
            'White-label Storefront',
            'Custom Integrations',
            'Dedicated Account Manager',
            'SLA Support',
            'Priority Support',
            'Email Support',
            'Everything in Starter',
            'Everything in Professional',
            'Unlimited Branches',
            'Unlimited Users',
        ];
    }

    public function revenue(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $like = '%'.addcslashes($q, '%_\\').'%';
        $shops = SalesLedger::query()
            ->join('tenants', 'tenants.id', '=', 'sales_ledger.tenantId')
            ->when($q !== '', function ($query) use ($like) {
                $query->where(function ($row) use ($like) {
                    $row->where('tenants.name', 'ilike', $like)
                        ->orWhere('tenants.slug', 'ilike', $like);
                });
            })
            ->groupBy('tenants.id', 'tenants.name', 'tenants.slug')
            ->select('tenants.id', 'tenants.name', 'tenants.slug')
            ->selectRaw('coalesce(sum(sales_ledger."grossRevenue"), 0) as gross')
            ->selectRaw('coalesce(sum(sales_ledger."netRevenue"), 0) as net')
            ->selectRaw('coalesce(sum(sales_ledger."totalVat"), 0) as vat')
            ->selectRaw('coalesce(sum(sales_ledger."grossProfit"), 0) as profit')
            ->selectRaw('count(*) as orders')
            ->orderByRaw('gross desc');
        $total = DB::query()->fromSub((clone $shops)->reorder()->select('tenants.id'), 'shops')->count();
        $pages = max(1, (int) ceil($total / 20));
        $page = min($pages, max(1, (int) $request->query('page', 1)));
        $totals = $this->ledgerTotals();

        return view('platform.revenue', [
            'shops' => $shops->forPage($page, 20)->get(),
            'q' => $q,
            'filtered' => $q !== '',
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'chart' => $this->monthBars(),
            'cards' => [
                ['label' => 'Gross revenue', 'value' => $this->money($totals['gross'])],
                ['label' => 'Net revenue', 'value' => $this->money($totals['net']), 'class' => 'rate'],
                ['label' => 'VAT', 'value' => $this->money($totals['vat']), 'class' => 'vat'],
                ['label' => 'Gross profit', 'value' => $this->money($totals['profit']), 'class' => 'gain'],
                ['label' => 'Orders', 'value' => number_format($totals['orders'])],
            ],
        ]);
    }

    public function analytics(Request $request)
    {
        [$period, $start, $end, $prevStart, $prevEnd, $label] = $this->platformWindow($request);
        $current = $this->ledgerTotals($start, $end);
        $previous = $this->ledgerTotals($prevStart, $prevEnd);
        $gross = $current['gross'];
        $chart = $start->isSameDay($end) ? $this->hourBars($start) : $this->dayBars($start, $end);

        return view('platform.analytics', [
            'period' => $period,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'periodLabel' => $label,
            'filtered' => $period !== '30',
            'chart' => $chart,
            'wide' => count($chart) > 16,
            'hourly' => $start->isSameDay($end),
            'kpis' => [
                'gross' => $gross,
                'cogs' => $current['cogs'],
                'profit' => $current['profit'],
                'margin' => $gross > 0 ? round(($current['profit'] / $gross) * 100, 1) : 0,
                'orders' => $current['orders'],
                'aov' => $current['orders'] > 0 ? $gross / $current['orders'] : 0,
                'vat' => $current['vat'],
                'net' => $current['net'],
                'cogsShare' => $gross > 0 ? round(($current['cogs'] / $gross) * 100, 1) : 0,
                'revenueGrowth' => $this->change($gross, $previous['gross']),
                'profitGrowth' => $this->change($current['profit'], $previous['profit']),
            ],
            'shops' => $this->topShops($start, $end),
            'channels' => $this->platformChannels($start, $end),
            'products' => $this->topProducts($start, $end),
            'dayparts' => $this->platformDayparts($start, $end),
        ]);
    }

    public function taxFilings(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['PENDING', 'FILED', 'OVERDUE'], true)) {
            $status = '';
        }
        $year = (string) $request->query('year', '');
        $years = TaxFiling::query()
            ->toBase()
            ->selectRaw('left(period, 4) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->mapWithKeys(fn ($value) => [(string) $value => (string) $value]);
        if (! preg_match('/^\d{4}$/', $year) || ! $years->has($year)) {
            $year = '';
        }

        $query = TaxFiling::query()->with(['tenant.subscription.plan']);
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->whereHas('tenant', fn ($tenant) => $tenant->where('name', 'ilike', $like));
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($year !== '') {
            $query->where('period', 'like', $year.'-%');
        }

        $summary = (clone $query)->toBase()->selectRaw("count(*) as filings, count(*) filter (where status = 'OVERDUE') as overdue, coalesce(sum(\"grossSales\"), 0) as gross, coalesce(sum(\"totalVat\"), 0) as vat")->first();
        [$filings, $total, $page, $pages] = $this->pageOf($query->orderByDesc('dueDate'), $request);

        return view('platform.tax-filings', [
            'filings' => $filings,
            'q' => $q,
            'status' => $status,
            'year' => $year,
            'years' => ['' => 'All'] + $years->all(),
            'filtered' => $q !== '' || $status !== '' || $year !== '',
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'cards' => [
                ['label' => 'Filings', 'value' => number_format($total)],
                ['label' => 'Overdue', 'value' => number_format((int) ($summary->overdue ?? 0)), 'class' => 'loss'],
                ['label' => 'VAT', 'value' => $this->money($summary->vat ?? 0), 'class' => 'vat'],
                ['label' => 'Gross sales', 'value' => $this->money($summary->gross ?? 0), 'class' => 'gain'],
            ],
        ]);
    }

    private function platformWindow(Request $request): array
    {
        $period = (string) $request->query('period', '30');
        if (! in_array($period, ['today', 'yesterday', '7', '30', '90', '365', 'custom'], true)) {
            $period = '30';
        }
        $now = Carbon::now('Africa/Lagos');
        if ($period === 'today') {
            $start = $now->copy()->startOfDay();
            $end = $now->copy()->endOfDay();
            $label = 'Today';
        } elseif ($period === 'yesterday') {
            $start = $now->copy()->subDay()->startOfDay();
            $end = $start->copy()->endOfDay();
            $label = 'Yesterday';
        } elseif ($period === 'custom') {
            $start = $this->lagosDay((string) $request->query('from', '')) ?? $now->copy()->subDays(29)->startOfDay();
            $end = ($this->lagosDay((string) $request->query('to', '')) ?? $now->copy())->endOfDay();
            if ($end->lt($start)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }
            if ((int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) > 365) {
                $end = $start->copy()->startOfDay()->addDays(365)->endOfDay();
            }
            $label = $start->isSameDay($end)
                ? $start->format('d M Y')
                : $start->format('d M').' – '.$end->format('d M Y');
        } else {
            $start = $now->copy()->subDays(((int) $period) - 1)->startOfDay();
            $end = $now->copy()->endOfDay();
            $label = match ($period) {
                '7' => 'Last 7 days',
                '90' => 'Last 90 days',
                '365' => 'Last year',
                default => 'Last 30 days',
            };
        }
        $span = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        $prevEnd = $start->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays(max(0, $span - 1))->startOfDay();

        return [$period, $start, $end, $prevStart, $prevEnd, $label];
    }

    private function ledgerTotals(?Carbon $start = null, ?Carbon $end = null): array
    {
        $query = SalesLedger::query()->toBase();
        if ($start && $end) {
            $query->whereBetween('createdAt', [$start->copy()->utc(), $end->copy()->utc()]);
        }
        $row = $query->selectRaw('coalesce(sum("grossRevenue"), 0) as gross, coalesce(sum("netRevenue"), 0) as net, coalesce(sum("totalVat"), 0) as vat, coalesce(sum("grossProfit"), 0) as profit, coalesce(sum("totalCogs"), 0) as cogs, count(*) as orders')->first();

        return [
            'gross' => (float) ($row->gross ?? 0),
            'net' => (float) ($row->net ?? 0),
            'vat' => (float) ($row->vat ?? 0),
            'profit' => (float) ($row->profit ?? 0),
            'cogs' => (float) ($row->cogs ?? 0),
            'orders' => (int) ($row->orders ?? 0),
        ];
    }

    private function change(float $current, float $previous): ?string
    {
        if ($previous <= 0) {
            return null;
        }
        $value = round((($current - $previous) / $previous) * 100, 1);
        $sign = $value > 0 ? '+' : ($value < 0 ? '−' : '');

        return $sign.number_format(abs($value), 1).'% vs previous';
    }

    private function monthBars(): array
    {
        $end = Carbon::now('Africa/Lagos')->endOfMonth();
        $start = $end->copy()->startOfMonth()->subMonths(5);
        $rows = SalesLedger::query()
            ->whereBetween('createdAt', [$start->copy()->utc(), $end->copy()->utc()])
            ->toBase()
            ->selectRaw("to_char(((\"createdAt\" AT TIME ZONE 'UTC') AT TIME ZONE 'Africa/Lagos'), 'YYYY-MM') as bucket, coalesce(sum(\"grossRevenue\"), 0) as total")
            ->groupByRaw("to_char(((\"createdAt\" AT TIME ZONE 'UTC') AT TIME ZONE 'Africa/Lagos'), 'YYYY-MM')")
            ->pluck('total', 'bucket');
        $totals = [];
        foreach ($rows as $month => $total) {
            $totals[substr((string) $month, 0, 7)] = (float) $total;
        }
        $peak = max(1.0, $totals === [] ? 0 : max($totals));
        $chart = [];
        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();
        $i = 0;
        while ($cursor->lte($last)) {
            $amount = $totals[$cursor->format('Y-m')] ?? 0;
            $chart[] = [
                'label' => $i === 0 || $cursor->month === 1 ? $cursor->format('M Y') : $cursor->format('M'),
                'title' => $cursor->format('F Y').' · '.$this->short($amount, true),
                'height' => $amount > 0 ? max(round(($amount / $peak) * 100, 1), 8) : 0,
            ];
            $cursor->addMonth();
            $i++;
        }

        return $chart;
    }

    private function topShops(Carbon $start, Carbon $end): array
    {
        $rows = SalesLedger::query()
            ->whereBetween('createdAt', [$start->copy()->utc(), $end->copy()->utc()])
            ->toBase()
            ->selectRaw('"tenantId" as tenant, coalesce(sum("grossRevenue"), 0) as revenue, count(*) as orders')
            ->groupByRaw('"tenantId"')
            ->orderByRaw('revenue desc')
            ->limit(10)
            ->get();
        $names = Tenant::query()->whereIn('id', $rows->pluck('tenant'))->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'id' => $row->tenant,
            'name' => $names[$row->tenant] ?? 'Shop',
            'revenue' => (float) $row->revenue,
            'orders' => (int) $row->orders,
        ])->all();
    }

    private function platformChannels(Carbon $start, Carbon $end): array
    {
        $labels = ['POS' => 'POS', 'ONLINE' => 'Online', 'WHATSAPP' => 'WhatsApp', 'INSTAGRAM' => 'Instagram', 'LINK' => 'Link'];
        $rows = SalesLedger::query()
            ->whereBetween('createdAt', [$start->copy()->utc(), $end->copy()->utc()])
            ->toBase()
            ->selectRaw('channel, coalesce(sum("grossRevenue"), 0) as revenue')
            ->groupBy('channel')
            ->pluck('revenue', 'channel');
        $total = max(1.0, (float) $rows->sum());

        return collect($labels)->map(fn ($label, $key) => [
            'label' => $label,
            'amount' => (float) ($rows[$key] ?? 0),
            'width' => round(((float) ($rows[$key] ?? 0) / $total) * 100, 1),
        ])->values()->all();
    }

    private function platformDayparts(Carbon $start, Carbon $end): array
    {
        $rows = SalesLedger::query()
            ->whereBetween('createdAt', [$start->copy()->utc(), $end->copy()->utc()])
            ->toBase()
            ->selectRaw("extract(hour from (\"createdAt\" AT TIME ZONE 'UTC') AT TIME ZONE 'Africa/Lagos') as hour, coalesce(sum(\"grossRevenue\"), 0) as revenue")
            ->groupByRaw("extract(hour from (\"createdAt\" AT TIME ZONE 'UTC') AT TIME ZONE 'Africa/Lagos')")
            ->pluck('revenue', 'hour');
        $parts = ['Morning' => 0.0, 'Afternoon' => 0.0, 'Evening' => 0.0, 'Night' => 0.0];
        foreach ($rows as $hour => $revenue) {
            $hour = (int) $hour;
            $name = $hour >= 6 && $hour < 12 ? 'Morning' : ($hour >= 12 && $hour < 17 ? 'Afternoon' : ($hour >= 17 && $hour < 21 ? 'Evening' : 'Night'));
            $parts[$name] += (float) $revenue;
        }
        $total = max(1.0, array_sum($parts));

        return collect($parts)->map(fn ($amount, $label) => [
            'label' => $label,
            'amount' => $amount,
            'width' => round(($amount / $total) * 100, 1),
        ])->values()->all();
    }

    private function topProducts(Carbon $start, Carbon $end): array
    {
        $rows = DB::select('select i."productName" as name, i.sku, coalesce(sum(i."lineTotal"), 0) as revenue, coalesce(sum(i."grossProfit"), 0) as profit from sales_ledger_items i inner join sales_ledger l on l.id = i."ledgerId" where l."createdAt" >= ? and l."createdAt" <= ? group by i."productName", i.sku order by revenue desc limit 10', [$start->copy()->utc()->toDateTimeString(), $end->copy()->utc()->toDateTimeString()]);

        return collect($rows)->map(function ($row) {
            $revenue = (float) $row->revenue;
            $profit = (float) $row->profit;
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0;

            return [
                'name' => $row->name ?: '—',
                'sku' => $row->sku,
                'revenue' => $revenue,
                'profit' => $profit,
                'margin' => $margin,
                'tone' => $margin >= 30 ? 'good' : ($margin >= 10 ? 'warn' : 'bad'),
            ];
        })->all();
    }

    public function users(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $shops = $this->shopOptions();
        $shop = (string) $request->query('shop', '');
        if ($shop !== '' && ! $shops->has($shop)) {
            $shop = '';
        }
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = '';
        }
        $query = User::query()->with(['tenant', 'role'])
            ->leftJoin('tenants', 'tenants.id', '=', 'users.tenantId')
            ->select('users.*');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($row) use ($like) {
                $row->where('users.name', 'ilike', $like)->orWhere('users.email', 'ilike', $like);
            });
        }
        if ($shop !== '') {
            $query->where('users.tenantId', $shop);
        }
        if ($status === 'active') {
            $query->where('users.isActive', true);
        }
        if ($status === 'inactive') {
            $query->where('users.isActive', false);
        }
        $query->orderByRaw('tenants.name asc nulls last')->orderBy('users.name');
        [$people, $total, $page, $pages] = $this->pageOf($query, $request);

        return view('platform.users', [
            'people' => $people,
            'q' => $q,
            'shop' => $shop,
            'shops' => ['' => 'All'] + $shops->all(),
            'status' => $status,
            'filtered' => $q !== '' || $shop !== '' || $status !== '',
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'self' => $this->user()->id,
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        $action = (string) $request->input('action');
        if ($action === 'save') {
            return $this->saveUser($request, $user);
        }
        if (in_array($action, ['active', 'inactive'], true)) {
            if ($user->id === $this->user()->id) {
                return back()->withErrors(['user' => 'You cannot change your own account.']);
            }
            $user->isActive = $action === 'active';
            $user->save();

            return back()->with('status', $user->isActive ? 'Marked active.' : 'Marked inactive.');
        }
        if ($action === 'reset') {
            return $this->sendReset($user);
        }
        if ($action === 'twofa') {
            if (! $user->twoFaEnabled) {
                return back()->with('status', 'Two-factor is already off.');
            }
            $user->forceFill(['twoFaEnabled' => false, 'twoFaSecret' => null])->save();

            return back()->with('status', 'Two-factor removed.');
        }

        abort(404);
    }

    public function destroyPlatformUser(User $user)
    {
        if ($user->id === $this->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        try {
            DB::transaction(function () use ($user) {
                DB::table('staff_payroll')->where('userId', $user->id)->delete();
                $email = $user->email;
                $user->delete();
                if ($email) {
                    TenantRegistration::query()->where('email', $email)->delete();
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['user' => 'This person could not be deleted.']);
        }

        return back()->with('status', 'Person deleted.');
    }

    public function customers(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $shops = $this->shopOptions();
        $shop = (string) $request->query('shop', '');
        if ($shop !== '' && ! $shops->has($shop)) {
            $shop = '';
        }
        $query = Customer::query()->with('tenant')
            ->leftJoin('tenants', 'tenants.id', '=', 'customers.tenantId')
            ->select('customers.*');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($row) use ($like) {
                $row->where('customers.name', 'ilike', $like)
                    ->orWhere('customers.phone', 'ilike', $like)
                    ->orWhere('customers.email', 'ilike', $like);
            });
        }
        if ($shop !== '') {
            $query->where('customers.tenantId', $shop);
        }
        $query->orderByRaw('tenants.name asc nulls last')->orderBy('customers.name');
        [$customers, $total, $page, $pages] = $this->pageOf($query, $request);

        return view('platform.customers', [
            'customers' => $customers,
            'q' => $q,
            'shop' => $shop,
            'shops' => ['' => 'All'] + $shops->all(),
            'filtered' => $q !== '' || $shop !== '',
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function catalogue(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $categoryRows = $this->catalogueCategoryRows();
        $categories = collect($categoryRows)->pluck('name', 'name');
        $category = (string) $request->query('category', '');
        if ($category !== '' && ! $categories->has($category)) {
            $category = '';
        }
        $query = CatalogueProduct::query()->orderByRaw('category asc nulls last')->orderBy('name');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($row) use ($like) {
                $row->where('name', 'ilike', $like)
                    ->orWhere('brand', 'ilike', $like)
                    ->orWhere('barcode', 'ilike', $like);
            });
        }
        if ($category !== '') {
            $query->where('category', $category);
        }
        [$items, $total, $page, $pages] = $this->pageOf($query, $request);

        return view('platform.catalogue', [
            'items' => $items,
            'q' => $q,
            'category' => $category,
            'categories' => ['' => 'All'] + $categories->all(),
            'categoryRows' => $categoryRows,
            'filtered' => $q !== '' || $category !== '',
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function storeCatalogue(Request $request)
    {
        CatalogueProduct::query()->create($this->catalogueInput($request) + [
            'createdBy' => $this->user()->id,
        ]);

        return redirect()->route('admin.catalogue')->with('status', 'Catalogue item added.');
    }

    public function storeCatalogueBulk(Request $request)
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:50'],
            'rows.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.brand' => ['nullable', 'string', 'max:120'],
            'rows.*.category' => ['nullable', 'string', 'max:120'],
            'rows.*.unit' => ['nullable', 'string', 'max:80'],
            'rows.*.barcode' => ['nullable', 'string', 'max:80'],
            'rows.*.description' => ['nullable', 'string', 'max:2000'],
        ]);

        $saved = 0;
        $skipped = 0;
        foreach ($data['rows'] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));
            if ($name === '' && $category === '') {
                $skipped++;

                continue;
            }
            if ($name === '' || $category === '') {
                return back()->withErrors(['rows' => 'Each filled row needs both a name and a category.'])->withInput(['form' => 'bulk']);
            }
            $clean = fn ($value) => ($value = trim((string) $value)) !== '' ? $value : null;
            CatalogueCategories::remember($category);
            CatalogueProduct::query()->create([
                'name' => $name,
                'brand' => $clean($row['brand'] ?? ''),
                'category' => $category,
                'unit' => $clean($row['unit'] ?? ''),
                'barcode' => $clean($row['barcode'] ?? ''),
                'description' => $clean($row['description'] ?? ''),
                'isActive' => true,
                'createdBy' => $this->user()->id,
            ]);
            $saved++;
        }

        if ($saved === 0) {
            return back()->withErrors(['rows' => 'Add at least one item with a name and category.'])->withInput(['form' => 'bulk']);
        }

        $msg = $saved.' catalogue item'.($saved === 1 ? '' : 's').' added.';
        if ($skipped > 0) {
            $msg .= ' '.$skipped.' empty row'.($skipped === 1 ? '' : 's').' skipped.';
        }

        return redirect()->route('admin.catalogue')->with('status', $msg);
    }

    public function storeCategoriesBulk(Request $request)
    {
        $data = $request->validate([
            'names' => ['required', 'array', 'min:1', 'max:50'],
            'names.*' => ['nullable', 'string', 'max:120'],
        ]);

        $saved = 0;
        $skipped = 0;
        foreach ($data['names'] as $raw) {
            $name = trim((string) $raw);
            if ($name === '') {
                $skipped++;

                continue;
            }
            if ($this->categoryTaken($name)) {
                $skipped++;

                continue;
            }
            CatalogueCategories::remember($name);
            $saved++;
        }

        if ($saved === 0) {
            return back()->withErrors(['names' => 'Add at least one new category name.'])->withInput(['form' => 'categories-bulk']);
        }

        $msg = $saved.' categor'.($saved === 1 ? 'y' : 'ies').' added.';
        if ($skipped > 0) {
            $msg .= ' '.$skipped.' blank or duplicate'.($skipped === 1 ? '' : 's').' skipped.';
        }

        return redirect()->route('admin.catalogue')->with('status', $msg);
    }

    public function catalogueTemplate()
    {
        $csv = "name,category,brand,unit,barcode,description\n"
            ."Indomie Chicken,Food,Indomie,per pack,1234567890123,Instant noodles\n"
            ."Peak Milk 400g,Beverages,Peak,tin,,Full cream powdered milk\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="salesdock-catalogue.csv"',
        ]);
    }

    public function importCatalogue(Request $request)
    {
        $file = $request->validate([
            'file' => ['required', 'file', 'extensions:csv', 'max:2048'],
        ])['file'];

        $parsed = collect(CatalogueCsv::parse($file->get()));
        $rows = $parsed->filter(fn ($row) => trim((string) ($row['name'] ?? '')) !== '' || trim((string) ($row['category'] ?? '')) !== '');

        if ($rows->isEmpty()) {
            return back()->withErrors(['file' => 'No rows with a name and category found in that CSV.']);
        }

        $incomplete = $rows->first(fn ($row) => trim((string) ($row['name'] ?? '')) === '' || trim((string) ($row['category'] ?? '')) === '');
        if ($incomplete) {
            return back()->withErrors(['file' => 'Every row needs both a name and a category. Download the template if you are unsure.']);
        }

        $saved = 0;
        foreach ($rows as $row) {
            $clean = fn ($value) => ($value = trim((string) $value)) !== '' ? $value : null;
            $category = trim((string) $row['category']);
            CatalogueCategories::remember($category);
            CatalogueProduct::query()->create([
                'name' => trim((string) $row['name']),
                'brand' => $clean($row['brand'] ?? ''),
                'category' => $category,
                'unit' => $clean($row['unit'] ?? ''),
                'barcode' => $clean($row['barcode'] ?? ''),
                'description' => $clean($row['description'] ?? ''),
                'isActive' => true,
                'createdBy' => $this->user()->id,
            ]);
            $saved++;
        }

        return redirect()->route('admin.catalogue')->with('status', $saved.' catalogue item'.($saved === 1 ? '' : 's').' imported from CSV.');
    }

    public function updateCatalogue(Request $request, CatalogueProduct $item)
    {
        $item->fill($this->catalogueInput($request))->save();

        return back()->with('status', 'Catalogue item saved.');
    }

    public function destroyCatalogue(CatalogueProduct $item)
    {
        $item->delete();

        return back()->with('status', 'Catalogue item deleted.');
    }

    public function storeCategory(Request $request)
    {
        $name = $this->categoryName($request);
        if ($this->categoryTaken($name)) {
            return back()->withErrors(['name' => 'That category already exists.'])->withInput(['form' => 'category', 'name' => $name]);
        }
        CatalogueCategories::remember($name);

        return back()->with('status', 'Category added.');
    }

    public function updateCategory(Request $request)
    {
        $from = trim((string) $request->input('from', ''));
        $name = $this->categoryName($request);
        if ($from === '') {
            return back()->withErrors(['name' => 'Choose a category to rename.'])->withInput(['form' => 'rename']);
        }
        if (mb_strtolower($from) !== mb_strtolower($name) && $this->categoryTaken($name)) {
            return back()->withErrors(['name' => 'That category already exists.'])->withInput(['form' => 'rename', 'from' => $from, 'name' => $name]);
        }
        CatalogueProduct::query()->where('category', $from)->update(['category' => $name]);
        CatalogueCategories::rename($from, $name);

        return back()->with('status', 'Category saved.');
    }

    public function destroyCategory(Request $request)
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            return back();
        }
        $stores = (int) DB::table('products')->where('category', $name)->selectRaw('count(distinct "tenantId") as stores')->value('stores');
        if ($stores > 0) {
            return back()->withErrors(['name' => $name.' is used by '.$stores.' '.($stores === 1 ? 'shop' : 'shops').', so it stays.']);
        }
        CatalogueProduct::query()->where('category', $name)->update(['category' => null]);
        CatalogueCategories::forget($name);

        return back()->with('status', 'Category deleted.');
    }

    public function auditLogs(Request $request)
    {
        $actions = $this->auditLabels();
        $action = (string) $request->query('action', '');
        if (! array_key_exists($action, $actions)) {
            $action = '';
        }
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        [$fromAt, $toAt, $from, $to] = $this->historyBounds($request);
        $query = AuditLog::query()->with(['tenant', 'user', 'supervisor']);
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($row) use ($like) {
                $row->whereHas('user', fn ($user) => $user->where('name', 'ilike', $like))
                    ->orWhereHas('tenant', fn ($tenant) => $tenant->where('name', 'ilike', $like)->orWhere('slug', 'ilike', $like));
            });
        }
        if ($action !== '') {
            $query->where('action', $action);
        }
        $this->applyBounds($query, 'timestamp', $fromAt, $toAt);
        [$logs, $total, $page, $pages] = $this->pageOf($query->latest('timestamp'), $request);

        return view('platform.audit-logs', [
            'logs' => $logs,
            'q' => $q,
            'action' => $action,
            'actions' => ['' => 'All'] + $actions,
            'labels' => $actions,
            'from' => $from,
            'to' => $to,
            'filtered' => $q !== '' || $action !== '' || $from !== '' || $to !== '',
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function activityLogs(Request $request)
    {
        $modules = $this->activityLabels();
        $module = (string) $request->query('module', '');
        if (! array_key_exists($module, $modules)) {
            $module = '';
        }
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        [$fromAt, $toAt, $from, $to] = $this->historyBounds($request);
        $query = ActivityLog::query()->with(['tenant', 'user']);
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($row) use ($like) {
                $row->where('description', 'ilike', $like)
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'ilike', $like))
                    ->orWhereHas('tenant', fn ($tenant) => $tenant->where('name', 'ilike', $like)->orWhere('slug', 'ilike', $like));
            });
        }
        if ($module !== '') {
            $query->where('module', $module);
        }
        $this->applyBounds($query, 'timestamp', $fromAt, $toAt);
        [$logs, $total, $page, $pages] = $this->pageOf($query->latest('timestamp'), $request);

        return view('platform.activity-logs', [
            'logs' => $logs,
            'q' => $q,
            'module' => $module,
            'modules' => ['' => 'All'] + $modules,
            'labels' => $modules,
            'from' => $from,
            'to' => $to,
            'filtered' => $q !== '' || $module !== '' || $from !== '' || $to !== '',
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function webhooks(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['processed', 'pending', 'failed'], true)) {
            $status = '';
        }
        $query = WebhookEvent::query();
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($row) use ($like) {
                $row->where('reference', 'ilike', $like)->orWhere('eventType', 'ilike', $like);
            });
        }
        if ($status === 'processed') {
            $query->where('processed', true);
        } elseif ($status === 'pending') {
            $query->where('processed', false)->whereNull('error');
        } elseif ($status === 'failed') {
            $query->whereNotNull('error');
        }
        $summary = (clone $query)->toBase()->selectRaw('count(*) as total, count(*) filter (where processed = false and error is null) as pending, count(*) filter (where error is not null) as failed')->first();
        [$events, $total, $page, $pages] = $this->pageOf($query->latest('createdAt'), $request);

        return view('platform.webhooks', [
            'events' => $events,
            'q' => $q,
            'status' => $status,
            'filtered' => $q !== '' || $status !== '',
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'cards' => [
                ['label' => 'Total', 'value' => number_format($total)],
                ['label' => 'Pending', 'value' => number_format((int) ($summary->pending ?? 0)), 'class' => 'vat'],
                ['label' => 'Failed', 'value' => number_format((int) ($summary->failed ?? 0)), 'class' => 'loss'],
            ],
        ]);
    }

    public function settings()
    {
        return view('platform.settings', [
            'notifyEmails' => PlatformSetting::adminNotifyEmails(),
            'contact' => PlatformSetting::supportContact(),
            'payment' => PlatformSetting::paymentForm(),
            'zoho' => PlatformSetting::zohoWhatsAppForm(),
            'googleAnalyticsId' => PlatformSetting::googleAnalyticsId(),
            'flutterwaveWebhook' => route('billing.webhook'),
            'paystackWebhook' => route('billing.webhook.paystack'),
        ]);
    }

    public function updateAnalytics(Request $request)
    {
        $data = $request->validate([
            'measurement_id' => ['nullable', 'string', 'max:32', 'regex:/^(G-[A-Z0-9]+)?$/i'],
        ], [
            'measurement_id.regex' => 'Use a GA4 measurement ID like G-XXXXXXXXXX, or leave blank to disable.',
        ]);

        PlatformSetting::writeGoogleAnalytics($data['measurement_id'] ?? null);

        return back()->with('status', 'Analytics settings saved.');
    }

    public function updateZohoWhatsApp(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'accounts_url' => ['nullable', 'url', 'max:255'],
            'api_base' => ['nullable', 'url', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'refresh_token' => ['nullable', 'string', 'max:1000'],
            'screen_name' => ['nullable', 'string', 'max:120'],
            'app_id' => ['nullable', 'string', 'max:80'],
            'department_id' => ['nullable', 'string', 'max:80'],
            'otp_template_id' => ['nullable', 'string', 'max:80'],
            'restock_template_id' => ['nullable', 'string', 'max:80'],
            'purchase_order_template_id' => ['nullable', 'string', 'max:80'],
            'salesiq_widget_code' => ['nullable', 'string', 'max:10000'],
        ]);

        PlatformSetting::writeZohoWhatsApp([
            'enabled' => $request->boolean('enabled'),
            'accounts_url' => $data['accounts_url'] ?? null,
            'api_base' => $data['api_base'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'client_secret' => $data['client_secret'] ?? null,
            'refresh_token' => $data['refresh_token'] ?? null,
            'screen_name' => $data['screen_name'] ?? null,
            'app_id' => $data['app_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'otp_template_id' => $data['otp_template_id'] ?? null,
            'restock_template_id' => $data['restock_template_id'] ?? null,
            'purchase_order_template_id' => $data['purchase_order_template_id'] ?? null,
            'salesiq_widget_code' => $data['salesiq_widget_code'] ?? '',
        ]);

        return back()->with('status', 'WhatsApp settings saved.');
    }

    public function updatePayments(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'in:flutterwave,paystack'],
            'platform_split_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'flutterwave_public_key' => ['nullable', 'string', 'max:255'],
            'flutterwave_secret_key' => ['nullable', 'string', 'max:255'],
            'flutterwave_secret_hash' => ['nullable', 'string', 'max:255'],
            'paystack_public_key' => ['nullable', 'string', 'max:255'],
            'paystack_secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        PlatformSetting::writePayment([
            'provider' => $data['provider'],
            'platform_split' => ((float) $data['platform_split_percent']) / 100,
            'flutterwave' => [
                'public_key' => $data['flutterwave_public_key'] ?? null,
                'secret_key' => $data['flutterwave_secret_key'] ?? null,
                'secret_hash' => $data['flutterwave_secret_hash'] ?? null,
            ],
            'paystack' => [
                'public_key' => $data['paystack_public_key'] ?? null,
                'secret_key' => $data['paystack_secret_key'] ?? null,
            ],
        ]);

        return back()->with('status', 'Payment settings saved.');
    }

    public function updateContact(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'hours' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'directions' => ['nullable', 'string', 'max:2000'],
            'mapUrl' => ['nullable', 'url:https', 'max:1000'],
        ], [
            'mapUrl.url' => 'The map link must be a full https:// link, e.g. from Google Maps → Share.',
        ]);

        PlatformSetting::write('supportContact', array_map(
            fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : null,
            $data,
        ));

        return back()->with('status', 'Contact details saved.');
    }

    public function updateSettings(Request $request)
    {
        $emails = collect(preg_split('/[\s,;]+/', (string) $request->input('notifyEmails', '')))
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();
        $request->merge(['emails' => $emails->all()]);
        $request->validate([
            'emails' => ['array', 'max:10'],
            'emails.*' => ['email:rfc', 'max:255'],
        ], [
            'emails.max' => 'Add up to 10 addresses.',
            'emails.*.email' => 'One of the addresses is not a valid email.',
        ]);

        PlatformSetting::write('adminNotifyEmails', $emails->all());

        return back()->with('status', 'Settings saved.');
    }

    public function checkIns(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $withReason = $request->query('reason') === '1';
        $query = AwayCheckIn::query()->with(['user', 'tenant'])->latest('createdAt');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($row) use ($like) {
                $row->where('reason', 'ilike', $like)
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like))
                    ->orWhereHas('tenant', fn ($tenant) => $tenant->where('name', 'ilike', $like));
            });
        }
        if ($withReason) {
            $query->whereNotNull('reason')->where('reason', '!=', '');
        }
        [$checkIns, $total, $page, $pages] = $this->pageOf($query, $request);

        return view('platform.check-ins', [
            'checkIns' => $checkIns,
            'q' => $q,
            'withReason' => $withReason,
            'filtered' => $q !== '' || $withReason,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    private function saveUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);
        $email = strtolower(trim($data['email']));
        $taken = User::query()->whereRaw('lower(email) = ?', [$email])->whereKeyNot($user->id)->exists();
        if ($taken) {
            return back()->withInput()->withErrors(['email' => 'A person with that email already exists.']);
        }
        $user->name = trim($data['name']);
        $user->email = $email;
        if (filled($data['password'] ?? null)) {
            $user->passwordHash = Hash::make($data['password'], ['rounds' => 12]);
        }
        $user->save();

        return back()->with('status', 'Person saved.');
    }

    private function sendReset(User $user)
    {
        if (! filled($user->email)) {
            return back()->withErrors(['user' => 'This person has no email.']);
        }
        if (trim((string) config('services.zeptomail.key')) === '' || trim((string) config('services.zeptomail.from')) === '') {
            return back()->withErrors(['user' => 'Password reset email is not configured.']);
        }

        PasswordResetToken::query()
            ->where('userId', $user->id)
            ->whereNull('usedAt')
            ->update(['usedAt' => now()]);
        $token = Str::random(64);
        PasswordResetToken::query()->create([
            'userId' => $user->id,
            'token' => $token,
            'expiresAt' => now()->addHour(),
            'createdAt' => now(),
        ]);
        AuthMail::send($user->email, $user->name, 'Reset your SalesDock password', [
            'preheader' => 'This password link expires in one hour.',
            'heading' => 'Reset your password',
            'kicker' => "Let's get you back into the account.",
            'paragraphs' => [
                'Hi <strong style="color:#111827;">'.e($user->name).'</strong>, a platform admin asked to reset the password for this SalesDock account.',
            ],
            'url' => route('password.reset', ['token' => $token]),
            'label' => 'Reset password',
            'note' => 'This link expires in 1 hour. If you were not expecting it, ignore this email and the password will stay the same.',
        ]);

        return back()->with('status', 'Reset email sent.');
    }

    private function catalogueInput(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:80'],
            'barcode' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $clean = fn ($value) => ($value = trim((string) $value)) !== '' ? $value : null;

        $category = trim($data['category']);
        CatalogueCategories::remember($category);

        return [
            'name' => trim($data['name']),
            'brand' => $clean($data['brand'] ?? ''),
            'category' => $category,
            'unit' => $clean($data['unit'] ?? ''),
            'barcode' => $clean($data['barcode'] ?? ''),
            'description' => $clean($data['description'] ?? ''),
            'isActive' => $request->boolean('isActive'),
        ];
    }

    private function categoryName(Request $request): string
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        return trim($data['name']);
    }

    private function categoryTaken(string $name): bool
    {
        if (CatalogueCategories::has($name)) {
            return true;
        }

        return CatalogueProduct::query()->whereRaw('lower(category) = ?', [mb_strtolower($name)])->exists();
    }

    private function catalogueCategoryRows(): array
    {
        $names = CatalogueProduct::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
        foreach (CatalogueCategories::extra() as $extra) {
            $known = $names->contains(fn ($name) => mb_strtolower((string) $name) === mb_strtolower($extra));
            if (! $known) {
                $names->push($extra);
            }
        }
        $names = $names->map(fn ($name) => trim((string) $name))->filter()->unique()->sort()->values();
        if ($names->isEmpty()) {
            return [];
        }
        $itemCounts = CatalogueProduct::query()
            ->whereIn('category', $names->all())
            ->selectRaw('category, count(*) as items')
            ->groupBy('category')
            ->pluck('items', 'category');
        $shops = DB::table('products')
            ->join('tenants', 'tenants.id', '=', 'products.tenantId')
            ->whereIn('products.category', $names->all())
            ->select('products.category', 'tenants.id', 'tenants.name')
            ->distinct()
            ->orderBy('tenants.name')
            ->get()
            ->groupBy('category');

        return $names->map(function ($name) use ($itemCounts, $shops) {
            $linked = collect($shops->get($name, []));

            return [
                'name' => $name,
                'items' => (int) ($itemCounts[$name] ?? 0),
                'stores' => $linked->count(),
                'shops' => $linked->take(20)->map(fn ($shop) => [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'url' => route('admin.tenants.show', $shop->id),
                ])->values()->all(),
            ];
        })->all();
    }

    private function shopOptions()
    {
        return Tenant::query()->orderBy('name')->pluck('name', 'id');
    }

    private function historyBounds(Request $request): array
    {
        $from = $this->lagosDay((string) $request->query('from', ''));
        $to = $this->lagosDay((string) $request->query('to', ''));
        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        } elseif ($to) {
            $to = $to->copy()->endOfDay();
        }

        return [$from, $to, $from?->toDateString() ?? '', $to?->toDateString() ?? ''];
    }

    private function applyBounds($query, string $column, ?Carbon $from, ?Carbon $to): void
    {
        if ($from) {
            $query->where($column, '>=', $from->copy()->utc());
        }
        if ($to) {
            $query->where($column, '<=', $to->copy()->utc());
        }
    }

    private function auditLabels(): array
    {
        return [
            'PRICE_OVERRIDE' => 'Price override',
            'DISCOUNT_APPLIED' => 'Discount',
            'LOGIN' => 'Sign in',
            'LOGOUT' => 'Sign out',
            'STOCK_ADJUST' => 'Stock adjustment',
            'PRODUCT_CREATED' => 'Product created',
            'PRODUCT_UPDATED' => 'Product updated',
            'PRODUCT_DELETED' => 'Product deleted',
            'ORDER_CREATED' => 'Order created',
            'ORDER_CANCELLED' => 'Order cancelled',
            'ORDER_RETURNED' => 'Order returned',
            'POS_CHECKOUT_SUCCESS' => 'Checkout',
            'POS_CART_RESERVED' => 'Cart reserved',
            'CASHIER_VOID' => 'Void',
            'REFUND_ISSUED' => 'Refund',
            'USER_CREATED' => 'Staff added',
            'USER_UPDATED' => 'Staff updated',
            'USER_DELETED' => 'Staff deactivated',
            'ROLE_CHANGED' => 'Role changed',
            'SETTINGS_UPDATED' => 'Settings updated',
            'PO_CREATED' => 'Purchase order created',
            'PO_SENT' => 'Purchase order sent',
            'PO_RECEIVED' => 'Purchase order received',
            'PROMO_CREATED' => 'Promotion created',
            'PROMO_EXPIRED' => 'Promotion expired',
            'TAX_SETTINGS_UPDATED' => 'Tax settings updated',
            'BULK_UPLOAD' => 'Bulk upload',
            'TWO_FA_ENABLED' => 'Two-factor on',
            'TWO_FA_DISABLED' => 'Two-factor off',
            'SUPERVISOR_PIN_USED' => 'Supervisor PIN',
        ];
    }

    private function activityLabels(): array
    {
        return [
            'POS' => 'POS',
            'INVENTORY' => 'Inventory',
            'ORDERS' => 'Orders',
            'PROCUREMENT' => 'Procurement',
            'FINANCIALS' => 'Financials',
            'STAFF' => 'Staff',
            'ANALYTICS' => 'Analytics',
            'SETTINGS' => 'Settings',
            'AUTH' => 'Sign-in',
            'STOREFRONT' => 'Storefront',
        ];
    }
}
