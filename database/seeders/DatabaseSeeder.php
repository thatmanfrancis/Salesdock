<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@salesdock.com';
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        $tenant = $user?->tenantId ? Tenant::query()->find($user->tenantId) : null;
        $tenant ??= Tenant::query()->where('slug', 'salesdock')->first()
            ?? Tenant::query()->where('email', $email)->first()
            ?? Tenant::query()->orderBy('createdAt')->first();

        if (! $tenant) {
            $tenant = Tenant::query()->create([
                'name' => 'SalesDock',
                'slug' => 'salesdock',
                'email' => $email,
                'currency' => 'NGN',
                'taxRate' => 7.5,
                'isActive' => true,
                'approvalStatus' => 'APPROVED',
                'approvedAt' => now(),
            ]);
        }

        $branch = Branch::query()->where('tenantId', $tenant->id)->where('isMain', true)->first()
            ?? Branch::query()->where('tenantId', $tenant->id)->first();
        if (! $branch) {
            $branch = Branch::query()->create([
                'tenantId' => $tenant->id,
                'name' => 'Main',
                'isMain' => true,
                'isActive' => true,
            ]);
        }

        $role = Role::query()->firstOrCreate(
            ['tenantId' => $tenant->id, 'name' => 'Super Admin'],
            [
                'role' => 'SUPER_ADMIN',
                'isDefault' => false,
                'permissions' => array_fill_keys(Permissions::KEYS, true),
            ]
        );
        $role->role = 'SUPER_ADMIN';
        $role->permissions = array_fill_keys(Permissions::KEYS, true);
        $role->save();

        $user ??= new User(['email' => $email]);
        if (! $user->exists) {
            $user->tenantId = $tenant->id;
            $user->branchId = $branch->id;
            $user->name = 'Platform Admin';
        }
        $user->roleId = $role->id;
        $user->passwordHash = Hash::make('Admin@1234', ['rounds' => 12]);
        $user->pin = hash('sha256', '1234');
        $user->pinIsDefault = false;
        $user->isSuperAdmin = true;
        $user->isActive = true;
        $user->twoFaEnabled = false;
        $user->twoFaSecret = null;
        $user->save();
    }
}
