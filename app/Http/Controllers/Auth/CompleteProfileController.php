<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StorefrontConfig;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Services\PaymentManager;
use App\Services\StorePayouts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CompleteProfileController extends Controller
{
    public const BUSINESS_TYPES = ['Supermarket', 'Fashion', 'Electronics', 'Pharmacy', 'Restaurant', 'Grocery', 'Beauty', 'Hardware', 'General'];

    /**
     * The welcome email links here; the form itself lives in a modal on the dashboard.
     */
    public function show(): RedirectResponse
    {
        $user = $this->user();
        if (! $user->mustCompleteProfile()) {
            return redirect($user->homePath());
        }

        return redirect($user->homePath())->with('profile_prompt_now', true);
    }

    public function store(Request $request, PaymentManager $payments, StorePayouts $payouts): RedirectResponse
    {
        $user = $this->user();
        if (! $user->mustCompleteProfile()) {
            return redirect($user->homePath());
        }

        $banks = $payments->active()->banks();
        $data = $request->validateWithBag('profile', [
            'ownerName' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'businessType' => ['required', 'string', Rule::in(self::BUSINESS_TYPES)],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:120'],
            'website' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:50'],
            'rcNumber' => ['nullable', 'string', 'max:50'],
            'bankCode' => ['required', 'string', 'max:20', Rule::in(array_keys($banks))],
            'bankAccount' => ['required', 'string', 'max:20'],
            'accountName' => ['nullable', 'string', 'max:255'],
        ], [], [
            'ownerName' => 'owner name',
            'businessType' => 'business type',
            'rcNumber' => 'RC number',
            'tin' => 'TIN',
            'bankCode' => 'bank',
            'bankAccount' => 'account number',
            'accountName' => 'account name',
        ]);
        $clean = fn (?string $value): ?string => ($value = trim((string) $value)) !== '' ? $value : null;
        $tenant = $user->tenant;

        try {
            DB::transaction(function () use ($data, $clean, $tenant, $user, $payouts, $banks): void {
                $tenant->forceFill([
                    'phone' => $clean($data['phone']),
                    'address' => $clean($data['address']),
                    'website' => $clean($data['website'] ?? null),
                    'tin' => $clean($data['tin'] ?? null),
                    'rcNumber' => $clean($data['rcNumber'] ?? null),
                    'profileCompletedAt' => now(),
                ])->save();

                $user->forceFill([
                    'name' => $clean($data['ownerName']),
                    'phone' => $clean($data['phone']),
                ])->save();

                Branch::query()->where('tenantId', $tenant->id)->where('isMain', true)->update([
                    'name' => $tenant->name.' — '.$clean($data['city']),
                    'address' => $clean($data['address']),
                    'phone' => $clean($data['phone']),
                ]);

                $store = StorefrontConfig::query()->firstOrNew(['tenantId' => $tenant->id]);
                if (! $store->exists) {
                    $store->storeName = $tenant->name;
                    $store->updatedAt = now();
                }
                if (! $store->tagline) {
                    $store->tagline = $data['businessType'].' Store';
                }
                $payouts->sync($tenant, $store, [
                    'bankCode' => $data['bankCode'],
                    'bankAccount' => $data['bankAccount'],
                    'accountName' => $data['accountName'] ?? null,
                    'bankName' => $banks[$data['bankCode']] ?? null,
                ]);

                self::registration($tenant)?->forceFill([
                    'ownerName' => $clean($data['ownerName']),
                    'phone' => $clean($data['phone']),
                    'address' => $clean($data['address']),
                    'city' => $clean($data['city']),
                    'businessType' => $data['businessType'],
                    'website' => $clean($data['website'] ?? null),
                    'tin' => $clean($data['tin'] ?? null),
                    'rcNumber' => $clean($data['rcNumber'] ?? null),
                ])->save();
            });
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['bankAccount' => $e->getMessage()])->errorBag('profile');
        }

        $hasPlan = Subscription::query()->where('tenantId', $tenant->id)->exists();

        return $hasPlan
            ? back()->with('status', 'Business details saved. Welcome to SalesDock!')
            : redirect()->route('billing')->with('status', 'Business details saved. Choose a plan to start selling.');
    }

    /**
     * Current values for the modal, so a half-filled shop sees what it already has.
     *
     * @return array<string, string|null>
     */
    public static function prefill(User $user): array
    {
        $tenant = $user->tenant;
        $registration = self::registration($tenant);

        $store = StorefrontConfig::query()->where('tenantId', $tenant->id)->first();

        return [
            'businessName' => $tenant->name,
            'ownerName' => $user->name !== $tenant->name ? $user->name : '',
            'phone' => $tenant->phone,
            'address' => $tenant->address,
            'city' => $registration?->city,
            'businessType' => $registration?->businessType,
            'website' => $tenant->website,
            'tin' => $tenant->tin,
            'rcNumber' => $tenant->rcNumber,
            'bankCode' => $store?->bankCode,
            'bankAccount' => $store?->bankAccount,
            'accountName' => $store?->accountName,
            'banks' => app(PaymentManager::class)->active()->banks(),
        ];
    }

    private static function registration(Tenant $tenant): ?TenantRegistration
    {
        return TenantRegistration::query()->whereRaw('lower(email) = ?', [strtolower((string) $tenant->email)])->first();
    }
}
