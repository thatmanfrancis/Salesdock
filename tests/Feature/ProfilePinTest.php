<?php

namespace Tests\Feature;

use App\Http\Middleware\ShareWorkspace;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProfilePinTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2026_09_23_000001_create_salesdock_schema.php',
            'database/migrations/2026_09_25_000001_create_support_tables.php',
            'database/migrations/2026_10_08_125551_add_deleted_at_to_tenants.php',
        ], '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_four_digit_pin_preserves_leading_zero_and_updates_only_current_user(): void
    {
        $user = $this->member();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->post(route('profile.update'), ['section' => 'pin', 'pin' => '0123', 'pin_confirmation' => '0123', 'current' => 'test-password'])
            ->assertRedirect(route('profile', ['section' => 'pin']))->assertSessionHasNoErrors();

        $this->assertSame(hash('sha256', '0123'), $user->fresh()->pin);
        $this->assertFalse($user->fresh()->pinIsDefault);
    }

    #[TestWith(['123'])]
    #[TestWith(['12345'])]
    #[TestWith(['123456'])]
    #[TestWith(['12a4'])]
    public function test_new_pin_must_have_exactly_four_digits(string $pin): void
    {
        $user = $this->member();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->post(route('profile.update'), ['section' => 'pin', 'pin' => $pin, 'pin_confirmation' => $pin, 'current' => 'test-password'])
            ->assertSessionHasErrors('pin');

        $this->assertSame(hash('sha256', '987654'), $user->fresh()->pin);
    }

    public function test_confirmation_must_match_new_pin(): void
    {
        $user = $this->member();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->post(route('profile.update'), ['section' => 'pin', 'pin' => '0123', 'pin_confirmation' => '0124', 'current' => 'test-password'])
            ->assertSessionHasErrors(['pin_confirmation' => 'The PINs do not match.']);

        $this->assertSame(hash('sha256', '987654'), $user->fresh()->pin);
    }

    public function test_current_password_is_required_to_change_pin(): void
    {
        $user = $this->member();

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->post(route('profile.update'), ['section' => 'pin', 'pin' => '0123', 'pin_confirmation' => '0123', 'current' => 'wrong-password'])
            ->assertSessionHasErrors('current');

        $this->assertSame(hash('sha256', '987654'), $user->fresh()->pin);
    }

    public function test_guests_cannot_change_pin(): void
    {
        $this->post(route('profile.update'), ['section' => 'pin'])->assertRedirect(route('login'));
    }

    public function test_pin_page_renders_two_four_box_fields_without_exposing_old_pin(): void
    {
        $user = $this->member();

        $response = $this->withoutMiddleware(ShareWorkspace::class)->actingAs($user)
            ->get(route('profile', ['section' => 'pin']))->assertOk()->assertDontSee('4–6 digits');

        $this->assertSame(8, substr_count($response->getContent(), 'data-pin-digit>'));
        $response->assertDontSee('987654');
    }

    private function member(): User
    {
        $tenant = Tenant::query()->create(['name' => 'Test shop', 'slug' => 'pin-shop']);
        $role = Role::query()->create(['tenantId' => $tenant->id, 'name' => 'Owner', 'role' => 'ADMIN', 'permissions' => []]);

        return User::query()->create([
            'tenantId' => $tenant->id, 'roleId' => $role->id,
            'name' => 'Member', 'email' => 'member@example.test', 'passwordHash' => Hash::make('test-password'),
            'pin' => hash('sha256', '987654'), 'pinIsDefault' => true,
        ]);
    }
}
