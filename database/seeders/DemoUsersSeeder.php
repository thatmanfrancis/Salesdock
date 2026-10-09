<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public const PASSWORD = 'Demo@1234';

    /**
     * Seed demo accounts for walking through signup, plans and the welcome back check-in.
     * Safe to run again: it resets each account to the same starting state.
     */
    public function run(TenantProvisioner $provisioner): void
    {
        $passwordHash = Hash::make(self::PASSWORD, ['rounds' => 12]);

        $this->shop($provisioner, $passwordHash, 'active@salesdock.test', 'Kemi Fashion House', 'Professional', now()->subDay());
        $this->shop($provisioner, $passwordHash, 'away@salesdock.test', 'Tunde Electronics', 'Starter', now()->subMonths(2));
        $this->shop($provisioner, $passwordHash, 'free@salesdock.test', 'Ada Grocery', null, now()->subDays(2), trialEnded: true);
        $this->shop($provisioner, $passwordHash, 'new@salesdock.test', 'Bola Pharmacy', null, null);
        $this->admin($passwordHash);
    }

    /**
     * A null plan seeds a fresh signup: on the free trial with business details still to fill in.
     * With $trialEnded the trial is already over, so the shop is locked until a plan is paid for.
     */
    private function shop(TenantProvisioner $provisioner, string $passwordHash, string $email, string $businessName, ?string $planName, ?Carbon $lastLoginAt, bool $trialEnded = false): void
    {
        $registration = TenantRegistration::query()->updateOrCreate(['email' => $email], [
            'businessName' => $businessName,
            'ownerName' => $businessName,
            'passwordHash' => $passwordHash,
            'emailVerified' => true,
            'emailVerifiedAt' => now(),
            'status' => 'APPROVED',
            'reviewedAt' => now(),
            'planSelected' => true,
            'verifyToken' => null,
            'planToken' => null,
        ]);

        $tenant = $provisioner->provision($registration);
        $isNewSignup = $planName === null && ! $trialEnded;
        $tenant->forceFill([
            'deletedAt' => null,
            'deletedBy' => null,
            'isActive' => true,
            'approvalStatus' => 'APPROVED',
            'phone' => $isNewSignup ? null : '+2348012345678',
            'address' => $isNewSignup ? null : '12 Allen Avenue, Ikeja',
            'profileCompletedAt' => $isNewSignup ? null : now(),
        ])->save();

        User::query()->where('tenantId', $tenant->id)->where('email', $email)->update([
            'name' => $businessName,
            'passwordHash' => $passwordHash,
            'isActive' => true,
            'twoFaEnabled' => false,
            'lastLoginAt' => $lastLoginAt,
        ]);

        if ($isNewSignup) {
            $provisioner->startTrial($tenant);

            return;
        }

        if ($trialEnded) {
            $provisioner->startTrial($tenant)?->forceFill([
                'status' => 'EXPIRED',
                'currentPeriodStart' => now()->subDays(TenantProvisioner::TRIAL_DAYS + 1),
                'currentPeriodEnd' => now()->subDay(),
                'trialEndsAt' => now()->subDay(),
                'nextBillingDate' => null,
            ])->save();

            return;
        }

        $plan = Plan::query()->where('name', $planName)->firstOrFail();
        $start = now()->startOfDay();
        $end = $start->copy()->addMonth();

        Subscription::query()->updateOrCreate(['tenantId' => $tenant->id], [
            'planId' => $plan->id,
            'status' => 'ACTIVE',
            'billingCycle' => 'MONTHLY',
            'currentPeriodStart' => $start,
            'currentPeriodEnd' => $end,
            'trialEndsAt' => null,
            'gracePeriodDays' => 7,
            'gracePeriodEndsAt' => $end->copy()->addDays(7),
            'lastRenewedAt' => $start,
            'nextBillingDate' => $end,
            'cancelAtPeriodEnd' => false,
            'cancelledAt' => null,
            'cancellationReason' => null,
        ]);
    }

    private function admin(string $passwordHash): void
    {
        $platform = User::query()->where('isSuperAdmin', true)->orderBy('createdAt')->first();
        $tenant = $platform ? Tenant::query()->find($platform->tenantId) : null;
        if (! $tenant) {
            $this->command?->warn('Run the DatabaseSeeder first so the platform tenant exists.');

            return;
        }

        $role = Role::query()->firstOrCreate(
            ['tenantId' => $tenant->id, 'name' => 'Super Admin'],
            [
                'role' => 'SUPER_ADMIN',
                'isDefault' => false,
                'permissions' => array_fill_keys(Permissions::KEYS, true),
            ]
        );

        User::query()->updateOrCreate(['email' => 'demo.admin@salesdock.test'], [
            'tenantId' => $tenant->id,
            'branchId' => $platform->branchId,
            'roleId' => $role->id,
            'name' => 'Demo Admin',
            'passwordHash' => $passwordHash,
            'isSuperAdmin' => true,
            'isActive' => true,
            'twoFaEnabled' => false,
            'lastLoginAt' => now(),
        ]);
    }
}
