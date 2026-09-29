<?php

namespace App\Http\Controllers\Merchant;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\BillingInvoice;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FinancialGoal;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesLedger;
use App\Models\StaffPayroll;
use App\Models\StorefrontConfig;
use App\Models\Subscription;
use App\Models\TaxFiling;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VatSettings;
use App\Services\BillingService;
use App\Services\Flutterwave;
use App\Services\TaxReturns;
use App\Support\Approval;
use App\Support\AuthMail;
use App\Support\Notices;
use App\Support\Password;
use App\Support\Permissions;
use App\Support\PlanFeatures;
use App\Support\Totp;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class OfficeController extends MerchantController
{
    public function ledger(Request $request)
    {
        abort_unless($this->managesFinancials(), 403);

        $tenantId = $this->tenantId();
        $channels = [
            '' => 'All',
            'POS' => 'POS',
            'ONLINE' => 'Online',
            'WHATSAPP' => 'WhatsApp',
            'INSTAGRAM' => 'Instagram',
            'LINK' => 'Link',
        ];
        $channel = (string) $request->query('channel', '');
        if (! array_key_exists($channel, $channels)) {
            $channel = '';
        }
        $staff = User::query()->where('tenantId', $tenantId)->orderBy('name')->pluck('name', 'id');
        $cashierId = (string) $request->query('cashier', '');
        if ($cashierId !== '' && ! $staff->has($cashierId)) {
            $cashierId = '';
        }
        $from = $this->ledgerDay((string) $request->query('from', ''));
        $to = $this->ledgerDay((string) $request->query('to', ''), true);

        $query = SalesLedger::query()->where('tenantId', $tenantId);
        if ($channel !== '') {
            $query->where('channel', $channel);
        }
        if ($cashierId !== '') {
            $query->where('cashierId', $cashierId);
        }
        if ($from) {
            $query->where('createdAt', '>=', $from);
        }
        if ($to) {
            $query->where('createdAt', '<=', $to);
        }

        $totals = (clone $query)->toBase()->selectRaw('coalesce(sum("grossRevenue"), 0) as gross_revenue, coalesce(sum("netRevenue"), 0) as net_revenue, coalesce(sum("totalVat"), 0) as total_vat, coalesce(sum("grossProfit"), 0) as gross_profit')->first();
        $chart = $this->ledgerChart(clone $query, $from, $to);
        $perPage = 20;
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));
        $entries = $query->with('cashier')->latest('createdAt')->forPage($page, $perPage)->get();

        return view('merchant.financials', [
            'entries' => $entries,
            'channels' => $channels,
            'channel' => $channel,
            'cashiers' => ['' => 'All'] + $staff->all(),
            'cashierId' => $cashierId,
            'from' => $from ? $from->format('Y-m-d') : '',
            'to' => $to ? $to->format('Y-m-d') : '',
            'page' => $page,
            'pages' => $pages,
            'filtered' => $channel !== '' || $cashierId !== '' || $from || $to,
            'chart' => $chart,
            'summary' => [
                ['label' => 'Gross revenue', 'value' => $this->money($totals->gross_revenue ?? 0)],
                ['label' => 'Net revenue', 'value' => $this->money($totals->net_revenue ?? 0)],
                ['label' => 'Total VAT', 'value' => $this->money($totals->total_vat ?? 0)],
                ['label' => 'Gross profit', 'value' => $this->money($totals->gross_profit ?? 0)],
            ],
        ]);
    }

    public function financialsPdf(Request $request)
    {
        abort_unless($this->managesFinancials(), 403);

        $tenantId = $this->tenantId();
        $channels = [
            '' => 'All',
            'POS' => 'POS',
            'ONLINE' => 'Online',
            'WHATSAPP' => 'WhatsApp',
            'INSTAGRAM' => 'Instagram',
            'LINK' => 'Link',
        ];
        $channel = (string) $request->query('channel', '');
        if (! array_key_exists($channel, $channels)) {
            $channel = '';
        }
        $staff = User::query()->where('tenantId', $tenantId)->orderBy('name')->pluck('name', 'id');
        $cashierId = (string) $request->query('cashier', '');
        if ($cashierId !== '' && ! $staff->has($cashierId)) {
            $cashierId = '';
        }
        $from = $this->ledgerDay((string) $request->query('from', ''));
        $to = $this->ledgerDay((string) $request->query('to', ''), true);

        $query = SalesLedger::query()->where('tenantId', $tenantId);
        if ($channel !== '') {
            $query->where('channel', $channel);
        }
        if ($cashierId !== '') {
            $query->where('cashierId', $cashierId);
        }
        if ($from) {
            $query->where('createdAt', '>=', $from);
        }
        if ($to) {
            $query->where('createdAt', '<=', $to);
        }

        $totals = (clone $query)->toBase()->selectRaw('coalesce(sum("grossRevenue"), 0) as gross_revenue, coalesce(sum("netRevenue"), 0) as net_revenue, coalesce(sum("totalVat"), 0) as total_vat, coalesce(sum("grossProfit"), 0) as gross_profit')->first();
        $entries = $query->with('cashier')->latest('createdAt')->limit(500)->get();
        $filters = [];
        if ($channel !== '') {
            $filters[] = 'Channel '.$channels[$channel];
        }
        if ($cashierId !== '') {
            $filters[] = 'Cashier '.($staff[$cashierId] ?? 'Selected');
        }
        if ($from) {
            $filters[] = 'From '.$from->timezone('Africa/Lagos')->format('d M Y');
        }
        if ($to) {
            $filters[] = 'To '.$to->timezone('Africa/Lagos')->format('d M Y');
        }

        $range = $from || $to
            ? trim(($from ? $from->timezone('Africa/Lagos')->format('d M Y') : 'Start').' – '.($to ? $to->timezone('Africa/Lagos')->format('d M Y') : 'Now'))
            : 'All time';

        return view('merchant.financials-report', $this->reportBrand([
            'title' => 'Financials report',
            'range' => $range,
            'backUrl' => route('financials', $request->query()),
            'summary' => [
                ['label' => 'Gross revenue', 'value' => $this->money($totals->gross_revenue ?? 0)],
                ['label' => 'Net revenue', 'value' => $this->money($totals->net_revenue ?? 0)],
                ['label' => 'Total VAT', 'value' => $this->money($totals->total_vat ?? 0)],
                ['label' => 'Gross profit', 'value' => $this->money($totals->gross_profit ?? 0)],
            ],
            'entries' => $entries,
            'filters' => $filters,
        ]));
    }

    public function storeGoal(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:REVENUE,ORDERS,PROFIT,NEW_CUSTOMERS'],
            'period' => ['required', 'in:DAILY,WEEKLY,MONTHLY,QUARTERLY,YEARLY,CUSTOM'],
            'targetValue' => ['required', 'numeric', 'min:0'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        FinancialGoal::query()->create($data + [
            'tenantId' => $this->tenantId(),
            'createdBy' => $this->user()->id,
        ]);

        return back()->with('status', 'Goal saved.');
    }

    public function showLedger(SalesLedger $ledger)
    {
        abort_unless($this->managesFinancials(), 403);
        abort_unless($ledger->tenantId === $this->tenantId(), 404);
        $ledger->load(['cashier', 'items']);

        return view('merchant.ledger', [
            'entry' => $ledger,
            'ref' => strtoupper(substr((string) $ledger->orderId, -8)),
        ]);
    }

    public function taxFilings(Request $request, TaxReturns $returns)
    {
        abort_unless($this->managesTaxFilings(), 403);

        $tenantId = $this->tenantId();
        $returns->ensure($tenantId);
        $currentYear = now('Africa/Lagos')->format('Y');
        $closed = now('Africa/Lagos')->startOfMonth()->subMonth()->format('Y-m');
        $year = (string) $request->query('year', $currentYear);
        if (! preg_match('/^\d{4}$/', $year)) {
            $year = $currentYear;
        }

        $years = TaxFiling::query()
            ->where('tenantId', $tenantId)
            ->where('period', '<=', $closed)
            ->toBase()
            ->selectRaw('left(period, 4) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');
        $yearOptions = [$currentYear => $currentYear];
        foreach ($years as $value) {
            $yearOptions[(string) $value] = (string) $value;
        }
        krsort($yearOptions);

        $query = TaxFiling::query()
            ->where('tenantId', $tenantId)
            ->where('period', 'like', $year.'-%')
            ->where('period', '<=', $closed);
        $perPage = 20;
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('merchant.tax-filings', [
            'filings' => $query->orderByDesc('period')->forPage($page, $perPage)->get(),
            'year' => $year,
            'years' => $yearOptions,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $year !== $currentYear,
        ]);
    }

    public function showTaxFiling(Request $request, TaxFiling $filing, TaxReturns $returns)
    {
        $filing = $this->guardTaxFiling($filing);
        $q = trim((string) $request->query('q', ''));
        $ledger = $returns->ledger($filing->tenantId, $filing->period);
        if ($q !== '') {
            $ledger->where(function ($inner) use ($q) {
                $inner->where('orderId', 'ilike', '%'.$q.'%')
                    ->orWhere('paymentMethod', 'ilike', '%'.$q.'%');
            });
        }
        $perPage = 20;
        $total = (clone $ledger)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('merchant.tax-filing', [
            'filing' => $filing,
            'entries' => $ledger->forPage($page, $perPage)->get(),
            'totals' => $returns->totals($filing->tenantId, $filing->period),
            'q' => $q,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '',
        ]);
    }

    public function taxFilingPdf(TaxFiling $filing, TaxReturns $returns)
    {
        $filing = $this->guardTaxFiling($filing);
        $filing->load('tenant');

        return view('merchant.tax-return', [
            'filing' => $filing,
            'tenant' => $filing->tenant,
            'entries' => $returns->ledger($filing->tenantId, $filing->period)->get(),
            'totals' => $returns->totals($filing->tenantId, $filing->period),
        ]);
    }

    public function fileTaxFiling(Request $request, TaxFiling $filing)
    {
        $filing = $this->guardTaxFiling($filing);
        abort_unless($filing->status !== 'FILED', 403);

        $data = $request->validate([
            'submissionRef' => ['required', 'string', 'max:120'],
            'proof' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'form' => ['nullable', 'string'],
        ]);
        $path = $request->file('proof')?->store('tax-proofs/'.$filing->tenantId, 'local');
        $filing->forceFill([
            'status' => 'FILED',
            'submissionRef' => trim($data['submissionRef']),
            'attachmentUrl' => $path,
            'filedAt' => now(),
            'totalTaxPaid' => $filing->totalVat,
        ])->save();
        $actor = $this->user();
        Approval::log($request, $filing->tenantId, $actor->id, $actor->id, 'TAX_SETTINGS_UPDATED', [
            'filingId' => $filing->id,
            'period' => $filing->period,
            'status' => 'FILED',
            'submissionRef' => $filing->submissionRef,
        ]);

        return redirect()->route('tax-filings')->with('status', 'Filing marked as submitted.');
    }

    public function expenses(Request $request)
    {
        abort_unless($this->managesFinancials(), 403);

        $tenantId = $this->tenantId();
        $categories = $this->expenseCategories();
        $today = now('Africa/Lagos')->startOfDay();
        $monthStart = $today->copy()->startOfMonth();
        $category = (string) $request->query('category', '');
        if (! array_key_exists($category, $categories)) {
            $category = '';
        }
        $from = $this->expenseDay((string) $request->query('from', '')) ?? $monthStart->copy();
        $to = $this->expenseDay((string) $request->query('to', ''), true) ?? $today->copy()->endOfDay();
        if ($from->gt($to)) {
            $from = $monthStart->copy();
            $to = $today->copy()->endOfDay();
        }
        $fromDay = $from->copy()->timezone('Africa/Lagos')->toDateString();
        $toDay = $to->copy()->timezone('Africa/Lagos')->toDateString();
        $from = $from->copy()->utc();
        $to = $to->copy()->utc();
        $defaultFrom = $monthStart->toDateString();
        $defaultTo = $today->toDateString();

        $range = Expense::query()->where('tenantId', $tenantId)->whereBetween('date', [$from, $to]);
        $spent = (clone $range)->toBase()->selectRaw('coalesce(sum(amount), 0) as total')->value('total');
        $pills = (clone $range)->toBase()
            ->selectRaw('category, coalesce(sum(amount), 0) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();
        $books = SalesLedger::query()
            ->where('tenantId', $tenantId)
            ->whereBetween('createdAt', [$from, $to])
            ->toBase()
            ->selectRaw('coalesce(sum("grossRevenue"), 0) as gross, coalesce(sum("totalCogs"), 0) as cogs, coalesce(sum("grossProfit"), 0) as profit')
            ->first();
        $gross = (float) ($books->gross ?? 0);
        $cogs = (float) ($books->cogs ?? 0);
        $profit = (float) ($books->profit ?? 0);
        $operating = (float) $spent;
        $net = $profit - $operating;

        $query = clone $range;
        if ($category !== '') {
            $query->where('category', $category);
        }
        $totalAmount = (clone $query)->toBase()->selectRaw('coalesce(sum(amount), 0) as total')->value('total');
        $perPage = 20;
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('merchant.expenses', [
            'expenses' => $query->orderByDesc('date')->orderByDesc('createdAt')->forPage($page, $perPage)->get(),
            'categories' => ['' => 'All'] + $categories,
            'labels' => $categories,
            'category' => $category,
            'from' => $fromDay,
            'to' => $toDay,
            'today' => $defaultTo,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $category !== '' || $fromDay !== $defaultFrom || $toDay !== $defaultTo,
            'totalAmount' => (float) $totalAmount,
            'pills' => $pills,
            'pnl' => [
                'gross' => $gross,
                'cogs' => $cogs,
                'profit' => $profit,
                'profitMargin' => $gross > 0 ? round(($profit / $gross) * 100, 1) : 0,
                'operating' => $operating,
                'net' => $net,
                'netMargin' => $gross > 0 ? round(($net / $gross) * 100, 1) : 0,
            ],
        ]);
    }

    public function storeExpense(Request $request)
    {
        abort_unless($this->managesFinancials(), 403);
        Expense::query()->create($this->expenseData($request) + [
            'tenantId' => $this->tenantId(),
            'createdBy' => $this->user()->id,
        ]);

        return back()->with('status', 'Expense logged.');
    }

    public function updateExpense(Request $request, Expense $expense)
    {
        $expense = $this->guardExpense($expense);
        $expense->forceFill($this->expenseData($request))->save();

        return back()->with('status', 'Expense saved.');
    }

    public function destroyExpense(Expense $expense)
    {
        $expense = $this->guardExpense($expense);
        $expense->delete();

        return back()->with('status', 'Expense deleted.');
    }

    public function analytics(Request $request)
    {
        abort_unless($this->managesAnalytics(), 403);

        $tenantId = $this->tenantId();
        [$period, $start, $end, $prevStart, $prevEnd] = $this->analyticsWindow($request);
        $current = $this->analyticsTotals($tenantId, $start, $end);
        $previous = $this->analyticsTotals($tenantId, $prevStart, $prevEnd);
        $gross = $current['gross'];

        return view('merchant.analytics', [
            'period' => $period,
            'periods' => [
                '7' => 'Last 7 days',
                '30' => 'Last 30 days',
                '90' => 'Last 90 days',
                '365' => 'Last year',
                'custom' => 'Custom range',
            ],
            'from' => $start->copy()->timezone('Africa/Lagos')->toDateString(),
            'to' => $end->copy()->timezone('Africa/Lagos')->toDateString(),
            'range' => $start->copy()->timezone('Africa/Lagos')->format('d M').' – '.$end->copy()->timezone('Africa/Lagos')->format('d M Y'),
            'filtered' => $period !== '30',
            'kpis' => [
                'gross' => $gross,
                'cogs' => $current['cogs'],
                'vat' => $current['vat'],
                'profit' => $current['profit'],
                'net' => $current['net'],
                'orders' => $current['orders'],
                'aov' => $current['orders'] > 0 ? $gross / $current['orders'] : 0,
                'margin' => $gross > 0 ? round(($current['profit'] / $gross) * 100, 1) : 0,
                'cogsShare' => $gross > 0 ? round(($current['cogs'] / $gross) * 100, 1) : 0,
                'revenueGrowth' => $this->growth($gross, $previous['gross']),
                'profitGrowth' => $this->growth($current['profit'], $previous['profit']),
            ],
            'chart' => $this->analyticsChart($tenantId, $start, $end),
            'channels' => $this->analyticsChannels($tenantId, $start, $end),
            'dayparts' => $this->analyticsDayparts($tenantId, $start, $end),
            'branches' => $this->analyticsBranches($tenantId, $start, $end),
            'products' => $this->analyticsProducts($tenantId, $start, $end),
        ]);
    }

    public function analyticsPdf(Request $request)
    {
        abort_unless($this->managesAnalytics(), 403);

        $tenantId = $this->tenantId();
        [$period, $start, $end, $prevStart, $prevEnd] = $this->analyticsWindow($request);
        $current = $this->analyticsTotals($tenantId, $start, $end);
        $previous = $this->analyticsTotals($tenantId, $prevStart, $prevEnd);
        $gross = $current['gross'];

        return view('merchant.analytics-report', $this->reportBrand([
            'title' => 'Analytics report',
            'range' => $start->copy()->timezone('Africa/Lagos')->format('d M Y').' – '.$end->copy()->timezone('Africa/Lagos')->format('d M Y'),
            'backUrl' => route('analytics', $request->query()),
            'kpis' => [
                'gross' => $gross,
                'cogs' => $current['cogs'],
                'vat' => $current['vat'],
                'profit' => $current['profit'],
                'net' => $current['net'],
                'orders' => $current['orders'],
                'aov' => $current['orders'] > 0 ? $gross / $current['orders'] : 0,
                'margin' => $gross > 0 ? round(($current['profit'] / $gross) * 100, 1) : 0,
                'revenueGrowth' => $this->growth($gross, $previous['gross']),
                'profitGrowth' => $this->growth($current['profit'], $previous['profit']),
            ],
            'channels' => $this->analyticsChannels($tenantId, $start, $end),
            'dayparts' => $this->analyticsDayparts($tenantId, $start, $end),
            'branches' => $this->analyticsBranches($tenantId, $start, $end),
            'products' => $this->analyticsProducts($tenantId, $start, $end),
            'period' => $period,
        ]));
    }

    public function staff(Request $request)
    {
        abort_unless($this->seesStaff(), 403);

        $tenantId = $this->tenantId();
        $q = trim((string) $request->query('q', ''));
        $roleId = (string) $request->query('role', '');
        $branchId = (string) $request->query('branch', '');
        $status = (string) $request->query('status', '');
        $roles = Role::query()->where('tenantId', $tenantId)->orderBy('name')->pluck('name', 'id');
        $branches = Branch::query()->where('tenantId', $tenantId)->orderBy('name')->pluck('name', 'id');
        $assignable = $roles->reject(fn ($name) => $name === 'Owner');
        [$slots, $limit] = $this->staffSlots($tenantId);

        $query = User::query()->with(['role', 'branch'])->where('tenantId', $tenantId);
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'ilike', '%'.$q.'%')
                    ->orWhere('email', 'ilike', '%'.$q.'%')
                    ->orWhere('phone', 'ilike', '%'.$q.'%');
            });
        }
        if ($roleId !== '' && $roles->has($roleId)) {
            $query->where('roleId', $roleId);
        }
        if ($branchId === 'none') {
            $query->whereNull('branchId');
        } elseif ($branchId !== '' && $branches->has($branchId)) {
            $query->where('branchId', $branchId);
        }
        if ($status === 'active') {
            $query->where('isActive', true);
        } elseif ($status === 'inactive') {
            $query->where('isActive', false);
        }

        $perPage = 20;
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('merchant.staff', [
            'people' => $query->orderBy('name')->forPage($page, $perPage)->get(),
            'q' => $q,
            'roleId' => $roleId = $roles->has($roleId) ? $roleId : '',
            'branchId' => $branchId = ($branchId === 'none' || $branches->has($branchId) ? $branchId : ''),
            'status' => $status = (in_array($status, ['active', 'inactive'], true) ? $status : ''),
            'roles' => $roles,
            'assignable' => $assignable,
            'branches' => $branches,
            'canWrite' => $this->writesStaff(),
            'slots' => $slots,
            'limit' => $limit,
            'full' => $slots >= $limit,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '' || $roleId !== '' || $branchId !== '' || $status !== '',
        ]);
    }

    public function storeStaff(Request $request)
    {
        abort_unless($this->writesStaff(), 403);

        $tenantId = $this->tenantId();
        [$slots, $limit] = $this->staffSlots($tenantId);
        if ($slots >= $limit) {
            return back()->withErrors(['staff' => 'Staff limit reached. Upgrade the plan to add more.'])->withInput();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:50'],
            'roleId' => ['required', 'string'],
            'branchId' => ['required', 'string'],
            'form' => ['nullable', 'string'],
        ]);
        $email = strtolower(trim($data['email']));
        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            return back()->withErrors(['email' => 'That email is already in use.'])->withInput();
        }

        $role = $this->staffRole($tenantId, $data['roleId']);
        $branch = Branch::query()->where('tenantId', $tenantId)->whereKey($data['branchId'])->first();
        if (! $branch) {
            return back()->withErrors(['branchId' => 'Choose a branch for this shop.'])->withInput();
        }

        $user = User::query()->create([
            'tenantId' => $tenantId,
            'name' => $data['name'],
            'email' => $email,
            'passwordHash' => Hash::make($data['password'], ['rounds' => 12]),
            'pin' => hash('sha256', '0000'),
            'pinIsDefault' => true,
            'phone' => $data['phone'] ?: null,
            'roleId' => $role->id,
            'branchId' => $branch->id,
            'isActive' => true,
        ]);

        Approval::log($request, $tenantId, $this->user()->id, $this->user()->id, 'USER_CREATED', [
            'newUserId' => $user->id,
            'email' => $email,
            'roleId' => $role->id,
        ]);

        $shop = Tenant::query()->whereKey($tenantId)->value('name') ?: 'your shop';
        AuthMail::send($email, $user->name, 'Welcome to the team at '.$shop, [
            'preheader' => 'Your SalesDock sign-in details are inside.',
            'heading' => 'Welcome to the team',
            'kicker' => $shop,
            'paragraphs' => [
                'Hi <strong style="color:#111827;">'.e($user->name).'</strong>, an account is ready for you at '.e($shop).'.',
            ],
            'summaryTitle' => 'Sign in',
            'summary' => [
                'Email' => $email,
                'Temporary password' => $data['password'],
                'PIN' => '0000',
                'Role' => $role->name,
            ],
            'url' => route('login'),
            'label' => 'Sign in',
            'note' => 'Change this password after you sign in. The PIN is 0000 until you set your own on the next login.',
        ]);

        return back()->with('status', 'Staff member added.');
    }

    public function showStaff(User $user)
    {
        abort_unless($this->seesStaff(), 403);
        abort_unless($user->tenantId === $this->tenantId(), 404);
        $user->load(['role', 'branch']);
        $tenantId = $this->tenantId();
        $actor = $this->user();

        return view('merchant.staff-member', [
            'member' => $user,
            'canWrite' => $this->writesStaff(),
            'isOwner' => $user->role?->name === 'Owner',
            'isSelf' => $user->id === $actor->id,
            'roles' => Role::query()->where('tenantId', $tenantId)->where('name', '!=', 'Owner')->orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::query()->where('tenantId', $tenantId)->orderBy('name')->pluck('name', 'id'),
            'activity' => ActivityLog::query()
                ->where('tenantId', $tenantId)
                ->where('userId', $user->id)
                ->latest('timestamp')
                ->limit(20)
                ->get(),
            'initials' => $this->initials($user->name),
        ]);
    }

    public function updateStaff(Request $request, User $user)
    {
        abort_unless($this->writesStaff(), 403);
        abort_unless($user->tenantId === $this->tenantId(), 404);
        $user->load('role');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'roleId' => ['nullable', 'string'],
            'branchId' => ['required', 'string'],
            'password' => ['nullable', 'string', 'min:8'],
            'form' => ['nullable', 'string'],
        ]);

        $tenantId = $this->tenantId();
        $branch = Branch::query()->where('tenantId', $tenantId)->whereKey($data['branchId'])->first();
        if (! $branch) {
            return back()->withErrors(['branchId' => 'Choose a branch for this shop.'])->withInput();
        }

        $isOwner = $user->role?->name === 'Owner';
        $roleId = $user->roleId;
        if (! $isOwner) {
            if ($user->id === $this->user()->id && ($data['roleId'] ?? '') !== $user->roleId) {
                return back()->withErrors(['roleId' => 'You cannot change your own role.'])->withInput();
            }
            $roleId = $this->staffRole($tenantId, (string) ($data['roleId'] ?? ''))->id;
        }

        $roleChanged = $roleId !== $user->roleId;
        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?: null,
            'roleId' => $roleId,
            'branchId' => $branch->id,
        ]);
        if (! empty($data['password'])) {
            $user->passwordHash = Hash::make($data['password'], ['rounds' => 12]);
        }
        $user->save();

        Approval::log($request, $tenantId, $this->user()->id, $this->user()->id, $roleChanged ? 'ROLE_CHANGED' : 'USER_UPDATED', [
            'targetUserId' => $user->id,
            'roleId' => $roleId,
        ]);

        return back()->with('status', 'Staff saved.');
    }

    public function toggleStaff(Request $request, User $user)
    {
        abort_unless($this->writesStaff(), 403);
        abort_unless($user->tenantId === $this->tenantId(), 404);
        $user->load('role');
        abort_if($user->id === $this->user()->id, 403);
        abort_if($user->role?->name === 'Owner', 403);

        $user->isActive = ! $user->isActive;
        $user->save();

        Approval::log($request, $this->tenantId(), $this->user()->id, $this->user()->id, $user->isActive ? 'USER_UPDATED' : 'USER_DELETED', [
            'targetUserId' => $user->id,
            'email' => $user->email,
            'isActive' => $user->isActive,
        ]);

        return back()->with('status', $user->isActive ? 'Staff reactivated.' : 'Staff deactivated.');
    }

    public function resetStaffPin(Request $request, User $user)
    {
        abort_unless($this->writesStaff(), 403);
        abort_unless($user->tenantId === $this->tenantId(), 404);

        $user->pin = hash('sha256', '0000');
        $user->pinIsDefault = true;
        $user->save();

        Approval::log($request, $this->tenantId(), $this->user()->id, $this->user()->id, 'USER_UPDATED', [
            'targetUserId' => $user->id,
            'resetPin' => true,
        ]);

        return back()->with('status', 'PIN reset.');
    }

    public function payroll(Request $request)
    {
        abort_unless($this->seesPayroll(), 403);

        $tenantId = $this->tenantId();
        $period = (string) $request->query('period', '');
        $staffId = (string) $request->query('staff', '');
        $status = (string) $request->query('status', '');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $period = '';
        }
        $people = User::query()->where('tenantId', $tenantId)->orderBy('name')->pluck('name', 'id');
        if (! $people->has($staffId)) {
            $staffId = '';
        }
        if (! in_array($status, ['paid', 'pending'], true)) {
            $status = '';
        }

        $query = StaffPayroll::query()->with('user.role')->where('tenantId', $tenantId);
        if ($period !== '') {
            $query->where('period', $period);
        }
        if ($staffId !== '') {
            $query->where('userId', $staffId);
        }
        if ($status === 'paid') {
            $query->where('isPaid', true);
        } elseif ($status === 'pending') {
            $query->where('isPaid', false);
        }

        $summary = (clone $query)->toBase()->selectRaw('coalesce(sum("netPay"), 0) as total, coalesce(sum(case when "isPaid" then "netPay" else 0 end), 0) as paid, count(*) filter (where not "isPaid") as pending, count(*) as payslips')->first();
        $perPage = 20;
        $total = (int) ($summary->payslips ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('merchant.payroll', [
            'rows' => $query->latest('createdAt')->forPage($page, $perPage)->get(),
            'period' => $period,
            'staffId' => $staffId,
            'status' => $status,
            'people' => $people,
            'summary' => [
                'total' => (float) ($summary->total ?? 0),
                'paid' => (float) ($summary->paid ?? 0),
                'pending' => (int) ($summary->pending ?? 0),
                'payslips' => $total,
            ],
            'page' => $page,
            'pages' => $pages,
            'filtered' => $period !== '' || $staffId !== '' || $status !== '',
        ]);
    }

    public function storePayroll(Request $request)
    {
        abort_unless($this->seesPayroll(), 403);

        $data = $this->payrollData($request);
        $tenantId = $this->tenantId();
        $person = $this->payrollPerson($tenantId, $data['userId']);
        if (StaffPayroll::query()->where('userId', $person->id)->where('period', $data['period'])->exists()) {
            return back()->withErrors(['period' => 'Payroll for '.$person->name.' is already recorded for '.$data['period'].'.'])->withInput();
        }

        StaffPayroll::query()->create($this->payrollAmounts($data) + [
            'tenantId' => $tenantId,
            'userId' => $person->id,
            'period' => $data['period'],
            'isPaid' => false,
        ]);

        return back()->with('status', 'Payslip recorded.');
    }

    public function updatePayroll(Request $request, StaffPayroll $payroll)
    {
        abort_unless($this->seesPayroll(), 403);
        abort_unless($payroll->tenantId === $this->tenantId(), 404);

        $data = $this->payrollData($request);
        $person = $this->payrollPerson($payroll->tenantId, $data['userId']);
        $clash = StaffPayroll::query()
            ->where('userId', $person->id)
            ->where('period', $data['period'])
            ->whereKeyNot($payroll->id)
            ->exists();
        if ($clash) {
            return back()->withErrors(['period' => 'Payroll for '.$person->name.' is already recorded for '.$data['period'].'.'])->withInput();
        }

        $payroll->fill($this->payrollAmounts($data) + [
            'userId' => $person->id,
            'period' => $data['period'],
        ]);
        $payroll->save();

        Approval::log($request, $payroll->tenantId, $this->user()->id, $this->user()->id, 'USER_UPDATED', [
            'payrollId' => $payroll->id,
            'userId' => $person->id,
            'period' => $data['period'],
        ]);

        return back()->with('status', 'Payslip saved.');
    }

    public function payPayroll(Request $request, StaffPayroll $payroll)
    {
        abort_unless($this->seesPayroll(), 403);
        abort_unless($payroll->tenantId === $this->tenantId(), 404);
        abort_if($payroll->isPaid, 403);

        $payroll->isPaid = true;
        $payroll->paidAt = Carbon::now();
        $payroll->save();

        Approval::log($request, $this->tenantId(), $this->user()->id, $this->user()->id, 'USER_UPDATED', [
            'payrollId' => $payroll->id,
            'userId' => $payroll->userId,
            'period' => $payroll->period,
            'paid' => true,
        ]);

        return back()->with('status', 'Payslip marked paid.');
    }

    public function printPayroll(StaffPayroll $payroll)
    {
        abort_unless($this->seesPayroll(), 403);
        abort_unless($payroll->tenantId === $this->tenantId(), 404);
        $payroll->load('user.role');

        return view('merchant.payslip', [
            'payroll' => $payroll,
            'tenant' => Tenant::query()->find($this->tenantId()),
        ]);
    }

    public function account()
    {
        $email = $this->user()->email;

        return $this->screen('My orders', [
            'columns' => ['When', 'Status', 'Total'],
            'rows' => Order::query()->where('customerEmail', $email)->latest()->limit(50)->get()->map(fn ($order) => [
                optional($order->createdAt)->format('d M Y'),
                e($order->status),
                $this->money($order->netAmount),
            ]),
        ]);
    }

    public function loyalty()
    {
        $customer = Customer::query()->where('email', $this->user()->email)->first();

        return $this->screen('Loyalty', [
            'stats' => [
                ['label' => 'Points', 'value' => $customer->loyaltyPoints ?? 0],
                ['label' => 'Spend', 'value' => $this->money($customer->totalSpend ?? 0)],
                ['label' => 'Orders', 'value' => $customer->totalOrders ?? 0],
            ],
        ]);
    }

    public function branches(Request $request)
    {
        abort_unless($this->seesBranches(), 403);

        $tenantId = $this->tenantId();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = '';
        }
        [$slots, $limit] = $this->branchSlots($tenantId);

        $query = Branch::query()->withCount('users as staffCount')->where('tenantId', $tenantId);
        if ($q !== '') {
            $query->where('name', 'ilike', '%'.$q.'%');
        }
        if ($status === 'active') {
            $query->where('isActive', true);
        } elseif ($status === 'inactive') {
            $query->where('isActive', false);
        }

        $perPage = 20;
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('merchant.branches', [
            'branches' => $query->orderByDesc('isMain')->orderBy('name')->forPage($page, $perPage)->get(),
            'q' => $q,
            'status' => $status,
            'slots' => $slots,
            'limit' => $limit,
            'full' => $slots >= $limit,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '' || $status !== '',
        ]);
    }

    public function storeBranch(Request $request)
    {
        abort_unless($this->seesBranches(), 403);

        $tenantId = $this->tenantId();
        [$slots, $limit] = $this->branchSlots($tenantId);
        if ($slots >= $limit) {
            return back()->withErrors(['branch' => 'Your plan allows '.$limit.' branch'.($limit === 1 ? '' : 'es').'.'])->withInput();
        }

        $data = $this->branchData($request);
        $main = $request->boolean('isMain');
        DB::transaction(function () use ($tenantId, $data, $main) {
            if ($main) {
                Branch::query()->where('tenantId', $tenantId)->where('isMain', true)->update(['isMain' => false]);
            }
            Branch::query()->create($data + [
                'tenantId' => $tenantId,
                'isMain' => $main,
                'isActive' => true,
            ]);
        });

        return back()->with('status', 'Branch added.');
    }

    public function updateBranch(Request $request, Branch $branch)
    {
        abort_unless($this->seesBranches(), 403);
        abort_unless($branch->tenantId === $this->tenantId(), 404);

        $data = $this->branchData($request);
        $main = $request->boolean('isMain');
        if ($branch->isMain && ! $main) {
            return back()->withErrors(['isMain' => 'The shop keeps one main branch. Mark another branch as main first.'])->withInput();
        }

        DB::transaction(function () use ($branch, $data, $main) {
            if ($main && ! $branch->isMain) {
                Branch::query()->where('tenantId', $branch->tenantId)->where('isMain', true)->update(['isMain' => false]);
            }
            $branch->fill($data + ['isMain' => $main || $branch->isMain]);
            $branch->save();
        });

        return back()->with('status', 'Branch saved.');
    }

    public function toggleBranch(Branch $branch)
    {
        abort_unless($this->seesBranches(), 403);
        abort_unless($branch->tenantId === $this->tenantId(), 404);

        if ($branch->isActive && $branch->isMain) {
            return back()->withErrors(['branch' => 'The main branch stays active.']);
        }

        if (! $branch->isActive) {
            [$slots, $limit] = $this->branchSlots($branch->tenantId);
            if ($slots >= $limit) {
                return back()->withErrors(['branch' => 'Your plan allows '.$limit.' branch'.($limit === 1 ? '' : 'es').'.']);
            }
        }

        $branch->isActive = ! $branch->isActive;
        $branch->save();

        return back()->with('status', $branch->isActive ? 'Branch activated.' : 'Branch deactivated.');
    }

    public function settings(Request $request)
    {
        abort_unless($this->seesSettings(), 403);

        $tenant = Tenant::query()->findOrFail($this->tenantId());
        $vat = VatSettings::query()->firstOrNew(['tenantId' => $tenant->id]);
        $store = StorefrontConfig::query()->firstOrNew(['tenantId' => $tenant->id]);
        $subscription = Subscription::query()->with('plan')->where('tenantId', $tenant->id)->first();
        $opensStorefront = PlanFeatures::includes(
            is_array($subscription?->plan?->features) ? $subscription->plan->features : [],
            ['Storefront / Online Store', 'White-label Storefront']
        );
        $section = (string) $request->query('section', 'business');
        if ($section === 'storefront' && ! $opensStorefront) {
            return redirect()->route('not-eligible');
        }
        if (! in_array($section, ['business', 'vat', 'storefront', 'subscription'], true)) {
            $section = 'business';
        }
        $days = null;
        if ($subscription?->currentPeriodEnd) {
            $seconds = $subscription->currentPeriodEnd->getTimestamp() - now()->getTimestamp();
            $days = (int) ceil($seconds / 86400);
        }

        return view('merchant.settings', [
            'section' => $section,
            'tenant' => $tenant,
            'vat' => $vat,
            'store' => $store,
            'subscription' => $subscription,
            'days' => $days,
            'opensStorefront' => $opensStorefront,
            'storeUrl' => $tenant->slug ? route('store.show', $tenant->slug) : null,
        ]);
    }

    public function updateSettings(Request $request)
    {
        abort_unless($this->seesSettings(), 403);

        $section = (string) $request->input('section', 'business');
        if ($section === 'storefront') {
            $features = Subscription::query()->with('plan')->where('tenantId', $this->tenantId())->first()?->plan?->features;
            if (! PlanFeatures::includes(is_array($features) ? $features : [], ['Storefront / Online Store', 'White-label Storefront'])) {
                return redirect()->route('not-eligible');
            }
        }

        return match ($section) {
            'vat' => $this->saveVat($request),
            'storefront' => $this->saveStorefront($request),
            default => $this->saveBusiness($request),
        };
    }

    public function roles(Request $request)
    {
        abort_unless($this->seesRoles(), 403);

        $builtin = ['Owner', 'Manager', 'Supervisor', 'Cashier', 'Staff Admin'];
        $roles = Role::query()
            ->where('tenantId', $this->tenantId())
            ->withCount('users as peopleCount')
            ->get()
            ->sortBy(fn ($role) => sprintf('%02d%s', ($index = array_search($role->name, $builtin, true)) === false ? 99 : $index, $role->name))
            ->values();
        $picked = $roles->firstWhere('id', (string) $request->query('role', ''));
        $locked = $picked?->name === 'Owner';
        $kept = ['Owner', 'Manager', 'Supervisor', 'Cashier', 'Staff Admin'];

        return view('merchant.roles', [
            'roles' => $roles,
            'picked' => $picked,
            'locked' => $locked,
            'flags' => $locked
                ? array_fill_keys(Permissions::KEYS, true)
                : Permissions::normalize($picked?->permissions ?? []),
            'details' => Permissions::details(),
            'levels' => [
                'CASHIER' => 'Cashier',
                'SUPERVISOR' => 'Supervisor',
                'MANAGER' => 'Manager',
                'ADMIN' => 'Admin',
            ],
            'canDelete' => $picked && ! in_array($picked->name, $kept, true) && ! $picked->isDefault && (int) $picked->peopleCount === 0,
        ]);
    }

    public function storeRole(Request $request)
    {
        abort_unless($this->seesRoles(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'in:CASHIER,SUPERVISOR,MANAGER,ADMIN'],
        ]);
        $name = trim($data['name']);
        if (strcasecmp($name, 'Owner') === 0) {
            return back()->withErrors(['name' => 'The name Owner is reserved.'])->withInput();
        }
        $tenantId = $this->tenantId();
        if (Role::query()->where('tenantId', $tenantId)->where('name', $name)->exists()) {
            return back()->withErrors(['name' => 'A role named '.$name.' already exists.'])->withInput();
        }

        $role = Role::query()->create([
            'tenantId' => $tenantId,
            'name' => $name,
            'role' => $data['role'],
            'isDefault' => false,
            'permissions' => match ($data['role']) {
                'CASHIER' => Permissions::defaults('Cashier'),
                'SUPERVISOR' => Permissions::defaults('Supervisor'),
                'MANAGER' => Permissions::defaults('Manager'),
                default => Permissions::defaults(''),
            },
        ]);

        return redirect()->route('roles', ['role' => $role->id])->with('status', 'Role added.');
    }

    public function updateRole(Request $request, Role $role)
    {
        abort_unless($this->seesRoles(), 403);
        abort_unless($role->tenantId === $this->tenantId(), 404);
        if ($role->name === 'Owner') {
            return back()->withErrors(['role' => 'The owner role stays on every ability.']);
        }

        $flags = array_fill_keys(Permissions::KEYS, false);
        foreach ($request->input('permissions', []) as $key) {
            if (array_key_exists($key, $flags)) {
                $flags[$key] = true;
            }
        }
        $role->permissions = $flags;
        $role->save();

        return redirect()->route('roles', ['role' => $role->id])->with('status', 'Permissions saved.');
    }

    public function destroyRole(Role $role)
    {
        abort_unless($this->seesRoles(), 403);
        abort_unless($role->tenantId === $this->tenantId(), 404);
        if (in_array($role->name, ['Owner', 'Manager', 'Supervisor', 'Cashier', 'Staff Admin'], true) || $role->isDefault) {
            return back()->withErrors(['role' => 'This role stays.']);
        }
        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'This role still has people on it.']);
        }
        $role->delete();

        return redirect()->route('roles')->with('status', 'Role deleted.');
    }

    public function auditLogs(Request $request)
    {
        abort_unless($this->seesAudit(), 403);

        $actions = $this->auditActions();
        $action = (string) $request->query('action', '');
        if (! array_key_exists($action, $actions)) {
            $action = '';
        }
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        [$fromAt, $toAt, $from, $to] = $this->historyWindow($request);

        $query = AuditLog::query()->where('tenantId', $this->tenantId())->with(['user', 'supervisor']);
        if ($q !== '') {
            $like = $this->likeTerm($q);
            $query->whereHas('user', fn ($user) => $user->where('name', 'ilike', $like));
        }
        if ($action !== '') {
            $query->where('action', $action);
        }
        $this->applyHistoryWindow($query, $fromAt, $toAt);
        [$page, $pages, $logs] = $this->historyPage($request, $query->latest('timestamp'));

        return view('merchant.audit-logs', [
            'logs' => $logs,
            'actions' => ['' => 'All'] + $actions,
            'labels' => $actions,
            'action' => $action,
            'q' => $q,
            'from' => $from,
            'to' => $to,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '' || $action !== '' || $from !== '' || $to !== '',
        ]);
    }

    public function activityLogs(Request $request)
    {
        abort_unless($this->seesActivity(), 403);

        $tenantId = $this->tenantId();
        $modules = $this->activityModules();
        $module = (string) $request->query('module', '');
        if (! array_key_exists($module, $modules)) {
            $module = '';
        }
        $people = User::query()->where('tenantId', $tenantId)->orderBy('name')->pluck('name', 'id');
        $person = (string) $request->query('person', '');
        if ($person !== '' && ! $people->has($person)) {
            $person = '';
        }
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        [$fromAt, $toAt, $from, $to] = $this->historyWindow($request);

        $query = ActivityLog::query()->where('tenantId', $tenantId)->with('user');
        if ($q !== '') {
            $query->where('description', 'ilike', $this->likeTerm($q));
        }
        if ($module !== '') {
            $query->where('module', $module);
        }
        if ($person !== '') {
            $query->where('userId', $person);
        }
        $this->applyHistoryWindow($query, $fromAt, $toAt);
        [$page, $pages, $logs] = $this->historyPage($request, $query->latest('timestamp'));

        return view('merchant.activity-logs', [
            'logs' => $logs,
            'modules' => ['' => 'All'] + $modules,
            'labels' => $modules,
            'module' => $module,
            'people' => ['' => 'All'] + $people->all(),
            'person' => $person,
            'roles' => [
                'CASHIER' => 'Cashier',
                'SUPERVISOR' => 'Supervisor',
                'MANAGER' => 'Manager',
                'ADMIN' => 'Admin',
                'SUPER_ADMIN' => 'Platform admin',
                'CLIENT' => 'Customer',
            ],
            'q' => $q,
            'from' => $from,
            'to' => $to,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $q !== '' || $module !== '' || $person !== '' || $from !== '' || $to !== '',
        ]);
    }

    public function notificationsUnread()
    {
        $user = $this->user();
        if (! $user->tenantId) {
            return response()->json(['unread' => 0]);
        }
        $unread = Notices::scope($user)->where('isRead', false)->count();

        return response()->json(['unread' => $unread]);
    }

    public function notifications(Request $request)
    {
        $user = $this->user();
        abort_unless($user->tenantId, 403);

        $limited = Notices::limited($user);
        $labels = Notices::types($limited);
        $type = (string) $request->query('type', '');
        if (! array_key_exists($type, $labels)) {
            $type = '';
        }
        $allowed = Notices::scope($user);
        $unread = (clone $allowed)->where('isRead', false)->count();
        $query = clone $allowed;
        if ($type !== '') {
            $query->where('type', $type);
        }
        $perPage = 20;
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('merchant.notifications', [
            'notes' => $query->latest('createdAt')->forPage($page, $perPage)->get(),
            'types' => ['' => 'All'] + $labels,
            'labels' => $labels,
            'type' => $type,
            'unread' => $unread,
            'page' => $page,
            'pages' => $pages,
            'filtered' => $type !== '',
        ]);
    }

    public function readNotifications()
    {
        $user = $this->user();
        abort_unless($user->tenantId, 403);
        Notices::scope($user)->where('isRead', false)->update([
            'isRead' => true,
            'readAt' => now(),
        ]);

        return back();
    }

    public function readNotification(Notification $notification)
    {
        $user = $this->user();
        abort_unless($user->tenantId && $notification->tenantId === $user->tenantId, 404);
        if (Notices::limited($user) && ! array_key_exists($notification->type, Notices::types(true))) {
            abort(404);
        }
        $notification->isRead = true;
        $notification->readAt = Carbon::now();
        $notification->save();

        return back();
    }

    public function profile(Request $request)
    {
        $user = $this->user()->loadMissing('role', 'branch');
        $sections = [
            'profile' => 'Profile',
            'password' => 'Password',
            'pin' => 'PIN',
            'two-factor' => 'Two-factor',
            'activity' => 'Activity',
        ];
        $section = (string) $request->query('section', 'profile');
        if (! array_key_exists($section, $sections)) {
            $section = 'profile';
        }
        $pending = $request->session()->get('profile_2fa_secret');
        $activity = collect();
        if ($section === 'activity' && $user->tenantId) {
            $activity = ActivityLog::query()
                ->where('tenantId', $user->tenantId)
                ->where('userId', $user->id)
                ->latest('timestamp')
                ->limit(20)
                ->get();
        }

        return view('merchant.profile', [
            'member' => $user,
            'sections' => $sections,
            'section' => $section,
            'initials' => $this->initials($user->name),
            'pendingSecret' => is_string($pending) ? $pending : null,
            'qr' => is_string($pending) ? Totp::qr(Totp::uri($pending, $user->email)) : null,
            'activity' => $activity,
            'modules' => $this->activityModules(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        return match ($request->input('section')) {
            'password' => $this->savePassword($request),
            'pin' => $this->savePin($request),
            'two-factor' => $this->saveTwoFactor($request),
            default => $this->saveProfileDetails($request),
        };
    }

    private function saveProfileDetails(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $user = $this->user();
        $user->name = trim($data['name']);
        $user->phone = trim((string) ($data['phone'] ?? '')) ?: null;
        if ($request->hasFile('photo')) {
            $folder = 'people/'.($user->tenantId ?: $user->id);
            $user->avatarUrl = $this->publicUrl($request->file('photo')->store($folder, 'public'));
        }
        $user->save();

        return redirect()->route('profile', ['section' => 'profile'])->with('status', 'Profile saved.');
    }

    private function savePassword(Request $request)
    {
        $data = $request->validate([
            'current' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
            'confirm' => ['required', 'same:password'],
        ], [
            'confirm.same' => 'The passwords do not match.',
        ]);
        $user = $this->user();
        if (! Password::check($data['current'], $user->passwordHash)) {
            return redirect()->route('profile', ['section' => 'password'])->withErrors(['current' => 'That password is not right.']);
        }
        $user->passwordHash = Hash::make($data['password'], ['rounds' => 12]);
        $user->save();

        return redirect()->route('profile', ['section' => 'password'])->with('status', 'Password changed.');
    }

    private function savePin(Request $request)
    {
        $data = $request->validate([
            'current' => ['required', 'string'],
            'pin' => ['required', 'digits_between:4,6'],
            'pin_confirmation' => ['required', 'same:pin'],
        ], [
            'pin_confirmation.same' => 'The PINs do not match.',
        ]);
        $user = $this->user();
        if (! Password::check($data['current'], $user->passwordHash)) {
            return redirect()->route('profile', ['section' => 'pin'])->withErrors(['current' => 'That password is not right.']);
        }
        $user->pin = hash('sha256', $data['pin']);
        $user->pinIsDefault = false;
        $user->save();

        return redirect()->route('profile', ['section' => 'pin'])->with('status', 'PIN updated.');
    }

    private function saveTwoFactor(Request $request)
    {
        $user = $this->user();
        $intent = (string) $request->input('intent');

        if ($intent === 'start' && ! $user->twoFaEnabled) {
            $request->session()->put('profile_2fa_secret', Totp::secret());

            return redirect()->route('profile', ['section' => 'two-factor']);
        }

        if ($intent === 'confirm' && ! $user->twoFaEnabled) {
            $secret = $request->session()->get('profile_2fa_secret');
            $data = $request->validate([
                'code' => ['required', 'digits:6'],
            ]);
            if (! is_string($secret) || ! Totp::verify($secret, $data['code'])) {
                return redirect()->route('profile', ['section' => 'two-factor'])->withErrors(['code' => 'That code is not right.']);
            }
            $user->twoFaSecret = $secret;
            $user->twoFaEnabled = true;
            $user->save();
            $request->session()->forget('profile_2fa_secret');
            $this->profileAudit($request, $user, 'TWO_FA_ENABLED');

            return redirect()->route('profile', ['section' => 'two-factor'])->with('status', 'Two-factor turned on.');
        }

        if ($intent === 'disable' && $user->twoFaEnabled) {
            $data = $request->validate([
                'current' => ['required', 'string'],
            ]);
            if (! Password::check($data['current'], $user->passwordHash)) {
                return redirect()->route('profile', ['section' => 'two-factor'])->withErrors(['current' => 'That password is not right.']);
            }
            $user->twoFaEnabled = false;
            $user->twoFaSecret = null;
            $user->save();
            $request->session()->forget('profile_2fa_secret');
            $this->profileAudit($request, $user, 'TWO_FA_DISABLED');

            return redirect()->route('profile', ['section' => 'two-factor'])->with('status', 'Two-factor turned off.');
        }

        return redirect()->route('profile', ['section' => 'two-factor']);
    }

    private function profileAudit(Request $request, User $user, string $action): void
    {
        if (! $user->tenantId) {
            return;
        }

        Approval::log($request, $user->tenantId, $user->id, $user->id, $action, []);
    }

    public function billing(Request $request, Flutterwave $flutterwave)
    {
        $tenantId = $this->tenantId();
        $cycles = [
            'MONTHLY' => 'Monthly',
            'QUARTERLY' => 'Quarterly',
            'ANNUALLY' => 'Annually',
        ];
        $hints = [
            'MONTHLY' => 'Billed every month',
            'QUARTERLY' => 'Billed every three months',
            'ANNUALLY' => 'Billed once a year',
        ];
        $subscription = Subscription::query()->with('plan')->where('tenantId', $tenantId)->first();
        $cycle = (string) $request->query('cycle', $subscription->billingCycle ?? 'MONTHLY');
        if (! array_key_exists($cycle, $cycles)) {
            $cycle = 'MONTHLY';
        }
        $perPage = 20;
        $invoiceQuery = BillingInvoice::query()->where('tenantId', $tenantId)->latest('createdAt');
        $total = (clone $invoiceQuery)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('merchant.billing', [
            'subscription' => $subscription,
            'plans' => Plan::query()->where('isActive', true)->orderBy('monthlyPrice')->get(),
            'invoices' => $invoiceQuery->forPage($page, $perPage)->get(),
            'cycles' => $cycles,
            'hints' => $hints,
            'cycle' => $cycle,
            'suffix' => ['MONTHLY' => '/mo', 'QUARTERLY' => '/qtr', 'ANNUALLY' => '/yr'],
            'statuses' => [
                'TRIALING' => 'Trialing',
                'ACTIVE' => 'Active',
                'PAST_DUE' => 'Past due',
                'SUSPENDED' => 'Suspended',
                'CANCELLED' => 'Cancelled',
                'EXPIRED' => 'Expired',
            ],
            'invoiceStatuses' => [
                'PENDING' => 'Pending',
                'PAID' => 'Paid',
                'FAILED' => 'Failed',
                'REFUNDED' => 'Refunded',
                'WAIVED' => 'Waived',
            ],
            'canChange' => $this->changesBilling(),
            'ready' => $flutterwave->ready(),
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function invoice(BillingInvoice $invoice)
    {
        abort_unless($invoice->tenantId === $this->tenantId(), 404);

        $plan = null;
        if ($invoice->subscriptionId) {
            $plan = Subscription::query()->with('plan')->find($invoice->subscriptionId)?->plan;
        }
        if (! $plan && $invoice->gatewayRef) {
            $plan = Plan::query()->whereKey($invoice->gatewayRef)->first();
        }

        return view('merchant.invoice', [
            'invoice' => $invoice,
            'tenant' => Tenant::query()->findOrFail($this->tenantId()),
            'plan' => $plan,
            'canChange' => $this->changesBilling(),
            'cycles' => [
                'MONTHLY' => 'Monthly',
                'QUARTERLY' => 'Quarterly',
                'ANNUALLY' => 'Annually',
            ],
            'invoiceStatuses' => [
                'PENDING' => 'Pending',
                'PAID' => 'Paid',
                'FAILED' => 'Failed',
                'REFUNDED' => 'Refunded',
                'WAIVED' => 'Waived',
            ],
        ]);
    }

    public function choosePlan(Request $request, Flutterwave $flutterwave, BillingService $billing)
    {
        abort_unless($this->changesBilling(), 403);

        $data = $request->validate([
            'plan' => ['required', 'string'],
            'cycle' => ['required', 'in:MONTHLY,QUARTERLY,ANNUALLY'],
        ]);
        $tenantId = $this->tenantId();
        $plan = Plan::query()->where('isActive', true)->whereKey($data['plan'])->first();
        if (! $plan) {
            return back()->withErrors(['plan' => 'Choose a plan from the list.']);
        }
        $subscription = Subscription::query()->where('tenantId', $tenantId)->first();
        if ($subscription && $subscription->planId === $plan->id && $subscription->billingCycle === $data['cycle']) {
            return redirect()->route('billing', ['cycle' => $data['cycle']]);
        }

        $amount = $this->planPrice($plan, $data['cycle']);
        if ($amount > 0 && ! $flutterwave->ready()) {
            return back()->withErrors(['plan' => 'Payment key missing.']);
        }

        $start = Carbon::now();
        $end = $this->periodEnd($start, $data['cycle']);
        $invoice = BillingInvoice::query()
            ->where('tenantId', $tenantId)
            ->where('status', 'PENDING')
            ->latest('createdAt')
            ->first();
        if ($invoice && $invoice->dueDate && $invoice->dueDate->lt(now())) {
            $invoice->update([
                'status' => 'FAILED',
                'failedAt' => now(),
                'failureReason' => 'Checkout session expired after 30 minutes',
            ]);
            $invoice = null;
        }
        $payload = [
            'billingCycle' => $data['cycle'],
            'amount' => $amount,
            'currency' => 'NGN',
            'gateway' => $amount > 0 ? 'FLUTTERWAVE' : null,
            'gatewayRef' => $plan->id,
            'periodStart' => $start,
            'periodEnd' => $end,
            'dueDate' => now()->addMinutes(30),
        ];
        if ($invoice) {
            $invoice->update($payload);
        } else {
            $invoice = BillingInvoice::query()->create($payload + [
                'tenantId' => $tenantId,
                'invoiceNumber' => 'INV-'.now()->format('YmdHis').'-'.strtoupper(str()->random(5)),
                'status' => 'PENDING',
            ]);
        }

        if ($amount <= 0) {
            $billing->activateFree($invoice, $plan->id);

            return redirect()->route('billing', ['cycle' => $data['cycle']])->with('status', 'Plan updated.');
        }

        return redirect()->route('billing.pay', $invoice);
    }

    private function changesBilling(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }
        if ($user->role?->name === 'Staff Admin') {
            return false;
        }

        return in_array($user->role?->role ?? 'CASHIER', ['MANAGER', 'ADMIN', 'SUPER_ADMIN'], true);
    }

    private function planPrice(Plan $plan, string $cycle): float
    {
        return (float) match ($cycle) {
            'QUARTERLY' => $plan->quarterlyPrice,
            'ANNUALLY' => $plan->annualPrice,
            default => $plan->monthlyPrice,
        };
    }

    private function periodEnd(\Illuminate\Support\Carbon $start, string $cycle): \Illuminate\Support\Carbon
    {
        return match ($cycle) {
            'QUARTERLY' => $start->copy()->addMonths(3),
            'ANNUALLY' => $start->copy()->addYear(),
            default => $start->copy()->addMonth(),
        };
    }

    private function payrollData(Request $request): array
    {
        return $request->validate([
            'userId' => ['required', 'string'],
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'baseSalary' => ['required', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'form' => ['nullable', 'string'],
            'payroll' => ['nullable', 'string'],
        ]);
    }

    private function payrollPerson(string $tenantId, string $userId): User
    {
        $person = User::query()->where('tenantId', $tenantId)->whereKey($userId)->first();
        if (! $person) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'userId' => 'Choose a staff member from this shop.',
            ]);
        }

        return $person;
    }

    private function payrollAmounts(array $data): array
    {
        $bonus = (float) ($data['bonus'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);

        return [
            'baseSalary' => $data['baseSalary'],
            'bonus' => $bonus,
            'deductions' => $deductions,
            'netPay' => (float) $data['baseSalary'] + $bonus - $deductions,
            'notes' => ($data['notes'] ?? '') !== '' ? $data['notes'] : null,
        ];
    }

    private function seesPayroll(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        $role = $user->role?->role ?? 'CASHIER';
        if (! in_array($role, ['SUPERVISOR', 'MANAGER', 'ADMIN', 'SUPER_ADMIN'], true)) {
            return false;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManagePayroll']);
    }

    private function seesSettings(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }
        if ($user->role?->name === 'Staff Admin') {
            return false;
        }

        $role = $user->role?->role ?? 'CASHIER';
        if (! in_array($role, ['MANAGER', 'ADMIN', 'SUPER_ADMIN'], true)) {
            return false;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageSettings']);
    }

    private function seesRoles(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin) {
            return true;
        }
        if ($user->role?->name === 'Staff Admin') {
            return false;
        }
        if ($user->role?->name === 'Owner') {
            return true;
        }

        $role = $user->role?->role ?? 'CASHIER';
        if (! in_array($role, ['MANAGER', 'ADMIN', 'SUPER_ADMIN'], true)) {
            return false;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageRoles']);
    }

    private function seesAudit(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin) {
            return true;
        }
        if ($user->role?->name === 'Staff Admin') {
            return false;
        }
        if ($user->role?->name === 'Owner') {
            return true;
        }

        return in_array($user->role?->role ?? 'CASHIER', ['MANAGER', 'ADMIN', 'SUPER_ADMIN'], true);
    }

    private function seesActivity(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        return in_array($user->role?->role ?? 'CASHIER', ['SUPERVISOR', 'MANAGER', 'ADMIN', 'SUPER_ADMIN'], true);
    }

    private function historyWindow(Request $request): array
    {
        $fromAt = $this->expenseDay((string) $request->query('from', ''));
        $toAt = $this->expenseDay((string) $request->query('to', ''), true);
        if ($fromAt && $toAt && $fromAt->gt($toAt)) {
            $start = $toAt->copy()->startOfDay();
            $end = $fromAt->copy()->endOfDay();
            $fromAt = $start;
            $toAt = $end;
        }

        return [$fromAt, $toAt, $fromAt?->toDateString() ?? '', $toAt?->toDateString() ?? ''];
    }

    private function applyHistoryWindow($query, $fromAt, $toAt): void
    {
        if ($fromAt) {
            $query->where('timestamp', '>=', $fromAt->copy()->utc());
        }
        if ($toAt) {
            $query->where('timestamp', '<=', $toAt->copy()->utc());
        }
    }

    private function historyPage(Request $request, $query): array
    {
        $perPage = 20;
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return [$page, $pages, $query->forPage($page, $perPage)->get()];
    }

    private function likeTerm(string $value): string
    {
        return '%'.addcslashes($value, '%_\\').'%';
    }

    private function auditActions(): array
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

    private function activityModules(): array
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

    private function saveBusiness(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:50'],
            'rcNumber' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $tenant = Tenant::query()->findOrFail($this->tenantId());
        $tenant->fill([
            'name' => $data['name'],
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
            'website' => $data['website'] ?: null,
            'tin' => $data['tin'] ?: null,
            'rcNumber' => $data['rcNumber'] ?: null,
        ]);
        if ($request->hasFile('logo')) {
            $tenant->logoUrl = $this->publicUrl($request->file('logo')->store('shops/'.$tenant->id, 'public'));
        }
        $tenant->save();
        $this->settingsAudit($request, 'business');

        return redirect()->route('settings', ['section' => 'business'])->with('status', 'Business saved.');
    }

    private function saveVat(Request $request)
    {
        $data = $request->validate([
            'globalVatRate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        $tenant = Tenant::query()->findOrFail($this->tenantId());
        $rate = $data['globalVatRate'];
        $tenant->taxRate = $rate;
        $tenant->save();
        VatSettings::query()->updateOrCreate(
            ['tenantId' => $tenant->id],
            ['globalVatRate' => $rate, 'isInclusive' => $request->boolean('isInclusive'), 'updatedAt' => now()]
        );
        $this->settingsAudit($request, 'vat');

        return redirect()->route('settings', ['section' => 'vat'])->with('status', 'VAT saved.');
    }

    private function saveStorefront(Request $request)
    {
        $data = $request->validate([
            'storeName' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'accentColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'customDomain' => ['nullable', 'string', 'max:255'],
            'metaTitle' => ['nullable', 'string', 'max:255'],
            'metaDescription' => ['nullable', 'string', 'max:500'],
            'bankName' => ['nullable', 'string', 'max:120'],
            'bankAccount' => ['nullable', 'string', 'max:20'],
            'accountName' => ['nullable', 'string', 'max:255'],
            'banner' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $tenant = Tenant::query()->findOrFail($this->tenantId());
        $store = StorefrontConfig::query()->firstOrNew(['tenantId' => $tenant->id]);
        $store->fill([
            'storeName' => $data['storeName'] ?: $tenant->name,
            'tagline' => $data['tagline'] ?: null,
            'accentColor' => $data['accentColor'] ?: '#16a34a',
            'customDomain' => $data['customDomain'] ?: null,
            'metaTitle' => $data['metaTitle'] ?: null,
            'metaDescription' => $data['metaDescription'] ?: null,
            'bankName' => $data['bankName'] ?: null,
            'bankAccount' => $data['bankAccount'] ?: null,
            'accountName' => $data['accountName'] ?: null,
            'isPublic' => $request->boolean('isPublic'),
            'allowGuestOrder' => $request->boolean('allowGuestOrder'),
            'updatedAt' => now(),
        ]);
        if ($request->hasFile('banner')) {
            $store->bannerImageUrl = $this->publicUrl($request->file('banner')->store('shops/'.$tenant->id, 'public'));
        }
        $store->save();
        $this->settingsAudit($request, 'storefront');

        return redirect()->route('settings', ['section' => 'storefront'])->with('status', 'Storefront saved.');
    }

    private function publicUrl(string $path): string
    {
        $disk = Storage::disk('public');
        if (! $disk instanceof FilesystemAdapter) {
            throw new \RuntimeException('Public disk is not available.');
        }

        return $disk->url($path);
    }

    private function settingsAudit(Request $request, string $section): void
    {
        Approval::log($request, $this->tenantId(), $this->user()->id, $this->user()->id, 'SETTINGS_UPDATED', [
            'section' => $section,
        ]);
    }

    private function seesBranches(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }
        if ($user->role?->name === 'Staff Admin') {
            return false;
        }

        $role = $user->role?->role ?? 'CASHIER';
        if (! in_array($role, ['MANAGER', 'ADMIN', 'SUPER_ADMIN'], true)) {
            return false;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageBranches']);
    }

    private function branchSlots(string $tenantId): array
    {
        $count = Branch::query()->where('tenantId', $tenantId)->where('isActive', true)->count();
        $limit = (int) (Subscription::query()->with('plan')->where('tenantId', $tenantId)->first()?->plan?->maxBranches ?? 1);

        return [$count, max(1, $limit)];
    }

    private function branchData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'form' => ['nullable', 'string'],
            'branch' => ['nullable', 'string'],
        ]);

        return [
            'name' => $data['name'],
            'address' => $data['address'] ?: null,
            'phone' => $data['phone'] ?: null,
        ];
    }

    private function seesStaff(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        $role = $user->role?->role ?? 'CASHIER';
        if (! in_array($role, ['SUPERVISOR', 'MANAGER', 'ADMIN', 'SUPER_ADMIN'], true)) {
            return false;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageStaff']);
    }

    private function writesStaff(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        $role = $user->role?->role ?? 'CASHIER';
        if (! in_array($role, ['MANAGER', 'ADMIN', 'SUPER_ADMIN'], true)) {
            return false;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageStaff']);
    }

    private function staffSlots(string $tenantId): array
    {
        $count = User::query()
            ->where('tenantId', $tenantId)
            ->whereHas('role', fn ($query) => $query->whereNotIn('role', ['ADMIN', 'SUPER_ADMIN']))
            ->count();
        $limit = (int) (Subscription::query()->with('plan')->where('tenantId', $tenantId)->first()?->plan?->maxUsers ?? 5);

        return [$count, max(1, $limit)];
    }

    private function staffRole(string $tenantId, string $roleId): Role
    {
        $role = Role::query()->where('tenantId', $tenantId)->whereKey($roleId)->first();
        if (! $role || $role->name === 'Owner') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'roleId' => 'Choose a role for this shop.',
            ]);
        }

        return $role;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : '?';
    }

    private function managesAnalytics(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        $role = $user->role?->role ?? 'CASHIER';
        if (! in_array($role, ['SUPERVISOR', 'MANAGER', 'ADMIN', 'SUPER_ADMIN'], true)) {
            return false;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canViewAnalytics']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function reportBrand(array $data): array
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId());
        $actor = $this->user();
        $owner = User::query()
            ->where('tenantId', $tenant->id)
            ->whereHas('role', fn ($query) => $query->where('name', 'Owner'))
            ->orderBy('createdAt')
            ->first();

        return array_merge($data, [
            'businessName' => $tenant->name,
            'ownerName' => $owner?->name ?: $actor->name,
            'preparedBy' => $actor->name,
            'generatedAt' => now('Africa/Lagos')->format('d M Y · h:i A').' WAT',
        ]);
    }

    private function analyticsWindow(Request $request): array
    {
        $period = (string) $request->query('period', '30');
        if (! in_array($period, ['7', '30', '90', '365', 'custom'], true)) {
            $period = '30';
        }
        $today = now('Africa/Lagos');
        if ($period === 'custom') {
            $start = $this->expenseDay((string) $request->query('from', '')) ?? $today->copy()->subDays(29)->startOfDay();
            $end = $this->expenseDay((string) $request->query('to', ''), true) ?? $today->copy()->endOfDay();
            if ($start->gt($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }
        } else {
            $start = $today->copy()->subDays(((int) $period) - 1)->startOfDay();
            $end = $today->copy()->endOfDay();
        }
        $days = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        $prevEnd = $start->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays(max(0, $days - 1))->startOfDay();

        return [$period, $start->copy()->utc(), $end->copy()->utc(), $prevStart->utc(), $prevEnd->utc()];
    }

    private function analyticsTotals(string $tenantId, Carbon $start, Carbon $end): array
    {
        $row = SalesLedger::query()
            ->where('tenantId', $tenantId)
            ->whereBetween('createdAt', [$start, $end])
            ->toBase()
            ->selectRaw('coalesce(sum("grossRevenue"), 0) as gross, coalesce(sum("totalCogs"), 0) as cogs, coalesce(sum("totalVat"), 0) as vat, coalesce(sum("grossProfit"), 0) as profit, coalesce(sum("netRevenue"), 0) as net, count(*) as orders')
            ->first();

        return [
            'gross' => (float) ($row->gross ?? 0),
            'cogs' => (float) ($row->cogs ?? 0),
            'vat' => (float) ($row->vat ?? 0),
            'profit' => (float) ($row->profit ?? 0),
            'net' => (float) ($row->net ?? 0),
            'orders' => (int) ($row->orders ?? 0),
        ];
    }

    private function growth(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function analyticsChart(string $tenantId, Carbon $start, Carbon $end): array
    {
        $rows = SalesLedger::query()
            ->where('tenantId', $tenantId)
            ->whereBetween('createdAt', [$start, $end])
            ->toBase()
            ->selectRaw("to_char((\"createdAt\" AT TIME ZONE 'UTC') AT TIME ZONE 'Africa/Lagos', 'YYYY-MM-DD') as day, coalesce(sum(\"grossRevenue\"), 0) as revenue")
            ->groupByRaw("to_char((\"createdAt\" AT TIME ZONE 'UTC') AT TIME ZONE 'Africa/Lagos', 'YYYY-MM-DD')")
            ->pluck('revenue', 'day');
        $buckets = [];
        foreach ($rows as $day => $revenue) {
            $buckets[substr((string) $day, 0, 10)] = (float) $revenue;
        }
        $rows = $buckets;
        $from = $start->copy()->timezone('Africa/Lagos')->startOfDay();
        $to = $end->copy()->timezone('Africa/Lagos')->startOfDay();
        $days = [];
        $peak = 0.0;
        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
            $amount = (float) ($rows[$cursor->toDateString()] ?? 0);
            $peak = max($peak, $amount);
            $days[] = ['day' => $cursor->copy(), 'amount' => $amount];
        }
        $peak = max($peak, 1);
        $step = count($days) > 24 ? (int) ceil(count($days) / 8) : 1;

        return [
            'wide' => count($days) > 16,
            'bars' => collect($days)->map(fn ($day, $index) => [
                'label' => $index % $step === 0 ? $day['day']->format('d M') : '',
                'value' => $this->money($day['amount']),
                'height' => $day['amount'] > 0 ? max(round(($day['amount'] / $peak) * 100, 1), 8) : 0,
            ])->all(),
        ];
    }

    private function analyticsChannels(string $tenantId, Carbon $start, Carbon $end): array
    {
        $labels = ['POS' => 'POS', 'ONLINE' => 'Online', 'WHATSAPP' => 'WhatsApp', 'INSTAGRAM' => 'Instagram', 'LINK' => 'Link'];
        $rows = SalesLedger::query()
            ->where('tenantId', $tenantId)
            ->whereBetween('createdAt', [$start, $end])
            ->toBase()
            ->selectRaw('channel, coalesce(sum("grossRevenue"), 0) as revenue')
            ->groupBy('channel')
            ->pluck('revenue', 'channel');
        $total = max(1, (float) $rows->sum());

        return collect($labels)->map(fn ($label, $key) => [
            'label' => $label,
            'amount' => (float) ($rows[$key] ?? 0),
            'width' => round(((float) ($rows[$key] ?? 0) / $total) * 100, 1),
        ])->values()->all();
    }

    private function analyticsDayparts(string $tenantId, Carbon $start, Carbon $end): array
    {
        $rows = SalesLedger::query()
            ->where('tenantId', $tenantId)
            ->whereBetween('createdAt', [$start, $end])
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
        $total = max(1, array_sum($parts));

        return collect($parts)->map(fn ($amount, $label) => [
            'label' => $label,
            'amount' => $amount,
            'width' => round(($amount / $total) * 100, 1),
        ])->values()->all();
    }

    private function analyticsBranches(string $tenantId, Carbon $start, Carbon $end): array
    {
        $rows = DB::select('select o."branchId" as branch, coalesce(sum(l."grossRevenue"), 0) as revenue from sales_ledger l left join orders o on o.id = l."orderId" where l."tenantId" = ? and l."createdAt" >= ? and l."createdAt" <= ? group by o."branchId" order by revenue desc', [$tenantId, $start->toDateTimeString(), $end->toDateTimeString()]);
        if ($rows === []) {
            return [];
        }
        $names = Branch::query()->whereIn('id', collect($rows)->pluck('branch')->filter())->pluck('name', 'id');
        $total = max(1, (float) collect($rows)->sum('revenue'));

        return collect($rows)->map(fn ($row) => [
            'label' => $row->branch ? ($names[$row->branch] ?? 'Branch') : 'Unassigned',
            'amount' => (float) $row->revenue,
            'width' => round(((float) $row->revenue / $total) * 100, 1),
        ])->all();
    }

    private function analyticsProducts(string $tenantId, Carbon $start, Carbon $end): array
    {
        $rows = DB::select('select i."productName" as name, i.sku, coalesce(sum(i."lineTotal"), 0) as revenue, coalesce(sum(i.quantity), 0) as qty, coalesce(sum(i."grossProfit"), 0) as profit, coalesce(sum(i."costPrice" * i.quantity), 0) as cogs from sales_ledger_items i inner join sales_ledger l on l.id = i."ledgerId" where l."tenantId" = ? and l."createdAt" >= ? and l."createdAt" <= ? group by i."productName", i.sku order by revenue desc limit 10', [$tenantId, $start->toDateTimeString(), $end->toDateTimeString()]);

        return collect($rows)->map(function ($row) {
            $revenue = (float) $row->revenue;
            $profit = (float) $row->profit;
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0;

            return [
                'name' => $row->name ?: '—',
                'sku' => $row->sku,
                'qty' => (int) $row->qty,
                'revenue' => $revenue,
                'cogs' => (float) $row->cogs,
                'profit' => $profit,
                'margin' => $margin,
                'tone' => $margin >= 30 ? 'good' : ($margin >= 10 ? 'warn' : 'bad'),
            ];
        })->all();
    }

    private function expenseCategories(): array
    {
        return [
            'RENT' => 'Rent',
            'UTILITIES' => 'Utilities',
            'SALARIES' => 'Salaries',
            'LOGISTICS' => 'Logistics',
            'MARKETING' => 'Marketing',
            'EQUIPMENT' => 'Equipment',
            'SUPPLIES' => 'Supplies',
            'TAXES' => 'Taxes',
            'INSURANCE' => 'Insurance',
            'PROFESSIONAL' => 'Professional',
            'OTHER' => 'Other',
        ];
    }

    private function expenseDay(string $value, bool $end = false): ?\Illuminate\Support\Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $value, 'Africa/Lagos');
        if (! $date || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $end ? $date->endOfDay() : $date->startOfDay();
    }

    private function expenseData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:'.implode(',', array_keys($this->expenseCategories()))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'form' => ['nullable', 'string'],
            'expense' => ['nullable', 'string'],
        ]);

        return [
            'title' => trim($data['title']),
            'category' => $data['category'],
            'amount' => $data['amount'],
            'date' => \Illuminate\Support\Carbon::parse($data['date'], 'Africa/Lagos')->startOfDay()->utc(),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    private function guardExpense(Expense $expense): Expense
    {
        abort_unless($this->managesFinancials(), 403);
        abort_unless($expense->tenantId === $this->tenantId(), 404);

        return $expense;
    }

    private function managesFinancials(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        $role = $user->role?->role ?? 'CASHIER';
        if (Permissions::level($role) < Permissions::level('MANAGER')) {
            return false;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageFinancials']);
    }

    private function managesTaxFilings(): bool
    {
        $user = $this->user()->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        if (($user->role?->role ?? 'CASHIER') !== 'ADMIN') {
            return false;
        }

        return ! empty(Permissions::normalize($user->role?->permissions ?? [])['canManageFinancials']);
    }

    private function guardTaxFiling(TaxFiling $filing): TaxFiling
    {
        abort_unless($this->managesTaxFilings(), 403);
        abort_unless($filing->tenantId === $this->tenantId(), 404);

        return $filing;
    }

    private function ledgerDay(string $value, bool $end = false): ?\Illuminate\Support\Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $value);
        if (! $date || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $end ? $date->endOfDay() : $date->startOfDay();
    }

    private function ledgerChart($query, ?\Illuminate\Support\Carbon $from, ?\Illuminate\Support\Carbon $to): array
    {
        $end = ($to ?? now())->copy()->startOfDay();
        $start = ($from ?? $end->copy()->subDays(13))->copy()->startOfDay();
        if ($start->gt($end)) {
            $start = $end->copy();
        }

        $rows = (clone $query)
            ->where('createdAt', '>=', $start->copy()->startOfDay())
            ->where('createdAt', '<=', $end->copy()->endOfDay())
            ->toBase()
            ->selectRaw('("createdAt")::date as day, coalesce(sum("grossProfit"), 0) as profit')
            ->groupByRaw('("createdAt")::date')
            ->get();
        $buckets = [];
        foreach ($rows as $row) {
            $buckets[\Illuminate\Support\Carbon::parse($row->day)->toDateString()] = (float) $row->profit;
        }

        $days = [];
        $peak = 0.0;
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $amount = $buckets[$cursor->toDateString()] ?? 0.0;
            $peak = max($peak, $amount);
            $days[] = ['day' => $cursor->copy(), 'amount' => $amount];
        }
        $peak = max($peak, 1);
        $step = count($days) > 24 ? (int) ceil(count($days) / 8) : 1;

        return [
            'range' => $start->format('d M').' – '.$end->format('d M Y'),
            'wide' => count($days) > 16,
            'bars' => collect($days)->map(fn ($day, $index) => [
                'label' => $index % $step === 0 ? $day['day']->format('d M') : '',
                'value' => $this->money($day['amount']),
                'height' => $day['amount'] > 0 ? max(round(($day['amount'] / $peak) * 100, 1), 8) : 0,
            ])->all(),
        ];
    }
}
