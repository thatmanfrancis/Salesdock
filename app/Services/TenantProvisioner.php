<?php

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\Branch;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\Role;
use App\Models\StorefrontConfig;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantProvisioner
{
    public function provision(TenantRegistration $registration): Tenant
    {
        $existing = Tenant::query()->where('email', $registration->email)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($registration) {
            $tenant = Tenant::query()->create([
                'name' => $registration->businessName,
                'slug' => $this->uniqueSlug($registration->businessName),
                'email' => $registration->email,
                'phone' => $registration->phone,
                'address' => $registration->address,
                'tin' => $registration->tin,
                'rcNumber' => $registration->rcNumber,
                'website' => $registration->website,
                'approvalStatus' => 'APPROVED',
                'approvedAt' => now(),
                'approvedBy' => $registration->reviewedBy,
            ]);

            $branch = Branch::query()->create([
                'tenantId' => $tenant->id,
                'name' => $registration->city
                    ? "{$registration->businessName} — {$registration->city}"
                    : "{$registration->businessName} — Main Branch",
                'address' => $registration->address,
                'phone' => $registration->phone,
                'isMain' => true,
            ]);

            $owner = Role::query()->create([
                'tenantId' => $tenant->id,
                'name' => 'Owner',
                'role' => 'ADMIN',
                'isDefault' => false,
                'permissions' => Permissions::defaults('Owner'),
            ]);

            foreach ([
                ['Manager', 'MANAGER', false],
                ['Supervisor', 'SUPERVISOR', false],
                ['Cashier', 'CASHIER', true],
                ['Staff Admin', 'ADMIN', false],
            ] as [$name, $role, $isDefault]) {
                Role::query()->create([
                    'tenantId' => $tenant->id,
                    'name' => $name,
                    'role' => $role,
                    'isDefault' => $isDefault,
                    'permissions' => Permissions::defaults($name),
                ]);
            }

            User::query()->create([
                'tenantId' => $tenant->id,
                'branchId' => $branch->id,
                'roleId' => $owner->id,
                'name' => $registration->ownerName,
                'email' => $registration->email,
                'passwordHash' => $registration->passwordHash,
                'isActive' => true,
            ]);

            StorefrontConfig::query()->create([
                'tenantId' => $tenant->id,
                'storeName' => $registration->businessName,
                'tagline' => $registration->businessType ? "{$registration->businessType} Store" : null,
                'accentColor' => '#16a34a',
                'isPublic' => true,
                'allowGuestOrder' => true,
                'metaTitle' => "{$registration->businessName} - Online Store",
                'metaDescription' => "Shop online at {$registration->businessName}. Quality products delivered to your doorstep.",
                'updatedAt' => now(),
            ]);

            return $tenant;
        });
    }

    public function price(Plan $plan, string $cycle): float
    {
        return (float) match ($cycle) {
            'QUARTERLY' => $plan->quarterlyPrice,
            'ANNUALLY' => $plan->annualPrice,
            default => $plan->monthlyPrice,
        };
    }

    public function periodEnd(Carbon $start, string $cycle): Carbon
    {
        return match ($cycle) {
            'QUARTERLY' => $start->copy()->addMonths(3),
            'ANNUALLY' => $start->copy()->addYear(),
            default => $start->copy()->addMonth(),
        };
    }

    public function openInvoice(string $tenantId): ?BillingInvoice
    {
        return BillingInvoice::query()
            ->where('tenantId', $tenantId)
            ->where('status', 'PENDING')
            ->latest('createdAt')
            ->first();
    }

    public function activateFree(Tenant $tenant, Plan $plan, string $cycle, BillingInvoice $invoice, Carbon $start, Carbon $end): void
    {
        $grace = $end->copy()->addDays(7);

        $subscription = Subscription::query()->updateOrCreate(
            ['tenantId' => $tenant->id],
            [
                'planId' => $plan->id,
                'status' => 'ACTIVE',
                'billingCycle' => $cycle,
                'currentPeriodStart' => $start,
                'currentPeriodEnd' => $end,
                'gracePeriodDays' => 7,
                'gracePeriodEndsAt' => $grace,
                'lastRenewedAt' => now(),
                'nextBillingDate' => $end,
                'cancelAtPeriodEnd' => false,
                'cancelledAt' => null,
                'cancellationReason' => null,
            ]
        );

        $invoice->update([
            'subscriptionId' => $subscription->id,
            'status' => 'PAID',
            'gateway' => 'CASH',
            'gatewayRef' => 'FREE-'.$invoice->id,
            'paidAt' => now(),
            'amount' => 0,
        ]);

        TenantRegistration::query()
            ->where('email', $tenant->email)
            ->where('planSelected', false)
            ->update([
                'planSelected' => true,
                'planToken' => null,
                'planTokenExp' => null,
            ]);

        Notification::query()->create([
            'tenantId' => $tenant->id,
            'type' => 'SUBSCRIPTION_RENEWED',
            'title' => 'Free Plan Activated',
            'message' => 'Your free SalesDock plan is active. Next review date: '.$end->toFormattedDateString().'.',
            'createdAt' => now(),
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'store';
        $base = Str::limit($base, 50, '');
        $slug = $base;
        $n = 0;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $n++;
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

}
