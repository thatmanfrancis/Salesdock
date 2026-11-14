<?php

namespace Tests\Feature;

use App\Http\Middleware\ShareWorkspace;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StaffAccountProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2026_09_23_000001_create_salesdock_schema.php',
        ], '--no-interaction' => true])->assertExitCode(0);
        $this->withoutMiddleware(ShareWorkspace::class);
    }

    #[TestWith(['MANAGER'])]
    #[TestWith(['ADMIN'])]
    public function test_delegated_staff_cannot_modify_owner_account(string $roleType): void
    {
        $tenant = Tenant::query()->create(['name' => 'Shop', 'slug' => 'shop']);
        $branch = Branch::query()->create(['tenantId' => $tenant->id, 'name' => 'Main']);
        $owner = $this->staffMember($tenant, 'Owner', 'ADMIN');
        $manager = $this->staffMember($tenant, 'Delegate', $roleType);

        $this->actingAs($manager)
            ->put(route('staff.update', $owner), ['name' => 'Changed', 'branchId' => $branch->id])
            ->assertForbidden();
        $this->post(route('staff.pin', $owner))->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $owner->id, 'name' => 'Owner', 'roleId' => $owner->roleId,
            'pin' => $owner->pin, 'pinIsDefault' => false, 'passwordHash' => $owner->passwordHash,
        ]);
    }

    public function test_owner_can_edit_own_details_and_delegate_can_edit_regular_staff(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Shop', 'slug' => 'shop']);
        $branch = Branch::query()->create(['tenantId' => $tenant->id, 'name' => 'Main']);
        $owner = $this->staffMember($tenant, 'Owner', 'ADMIN');
        $manager = $this->staffMember($tenant, 'Delegate', 'MANAGER');
        $cashier = $this->staffMember($tenant, 'Cashier', 'CASHIER');

        $this->actingAs($owner)->put(route('staff.update', $owner), [
            'name' => 'Shop owner', 'branchId' => $branch->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($manager)->put(route('staff.update', $cashier), [
            'name' => 'Shop cashier', 'branchId' => $branch->id, 'roleId' => $cashier->roleId,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Shop owner', $owner->fresh()->name);
        $this->assertSame('Shop cashier', $cashier->fresh()->name);
    }

    public function test_owner_protection_preserves_cross_shop_not_found_response(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Shop', 'slug' => 'shop']);
        $otherTenant = Tenant::query()->create(['name' => 'Other shop', 'slug' => 'other-shop']);
        $manager = $this->staffMember($tenant, 'Delegate', 'MANAGER');
        $owner = $this->staffMember($otherTenant, 'Owner', 'ADMIN');

        $this->actingAs($manager)->put(route('staff.update', $owner))->assertNotFound();
        $this->post(route('staff.pin', $owner))->assertNotFound();
    }

    public function test_merchant_admin_cannot_access_platform_registration_review(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Shop', 'slug' => 'shop']);
        $admin = $this->staffMember($tenant, 'Delegate', 'ADMIN');

        $this->actingAs($admin)->get(route('admin.registrations'))->assertForbidden();
    }

    public function test_delegate_cannot_edit_own_role_or_grant_permissions_outside_their_role(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Shop', 'slug' => 'shop']);
        $delegate = $this->staffMember($tenant, 'Delegate', 'MANAGER');
        $delegate->role->update(['permissions' => ['canManageRoles' => true, 'canUsePOS' => true]]);
        $cashier = $this->staffMember($tenant, 'Cashier', 'CASHIER');
        $cashier->role->update(['permissions' => []]);

        $this->actingAs($delegate)->put(route('roles.update', $delegate->role), [
            'permissions' => ['canManageRoles'],
        ])->assertForbidden();
        $this->put(route('roles.update', $cashier->role), [
            'permissions' => ['canManageSettings'],
        ])->assertForbidden();
        $this->assertSame([], $cashier->role->fresh()->permissions);
        $this->put(route('roles.update', $cashier->role), [
            'permissions' => ['canUsePOS'],
        ])->assertSessionHasNoErrors();
        $this->assertTrue($cashier->role->fresh()->permissions['canUsePOS']);
    }

    public function test_manager_cannot_assign_or_modify_a_higher_role(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Shop', 'slug' => 'shop']);
        $branch = Branch::query()->create(['tenantId' => $tenant->id, 'name' => 'Main']);
        $manager = $this->staffMember($tenant, 'Delegate', 'MANAGER');
        $admin = $this->staffMember($tenant, 'Admin staff', 'ADMIN');
        $cashier = $this->staffMember($tenant, 'Cashier', 'CASHIER');
        $payload = ['name' => 'Changed', 'branchId' => $branch->id];

        $this->actingAs($manager)->put(route('staff.update', $cashier), $payload + ['roleId' => $admin->roleId])->assertForbidden();
        $this->put(route('staff.update', $admin), $payload + ['roleId' => $cashier->roleId])->assertForbidden();
        $this->assertSame('Cashier', $cashier->fresh()->name);
        $this->assertSame('Admin staff', $admin->fresh()->name);
    }

    private function staffMember(Tenant $tenant, string $name, string $roleType): User
    {
        $role = Role::query()->create([
            'tenantId' => $tenant->id, 'name' => $name, 'role' => $roleType,
            'permissions' => ['canManageStaff' => true],
        ]);

        return User::query()->create([
            'tenantId' => $tenant->id, 'roleId' => $role->id, 'name' => $name,
            'email' => strtolower($name).'@example.test', 'passwordHash' => bcrypt('test-password'),
            'pin' => hash('sha256', '7391'), 'pinIsDefault' => false,
        ]);
    }
}
