<?php

namespace Tests\Feature;

use App\Models\PasswordResetToken;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Services\ZohoWhatsApp;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class VerificationRecoveryTest extends TestCase
{
    public function test_meta_sends_operational_templates_with_nigerian_phone_numbers(): void
    {
        config([
            'services.meta_whatsapp.enabled' => true,
            'services.meta_whatsapp.api_version' => 'v27.0',
            'services.meta_whatsapp.phone_number_id' => '12345',
            'services.meta_whatsapp.access_token' => 'test-token',
            'services.meta_whatsapp.restock_template' => 'salesdock_restock',
            'services.meta_whatsapp.purchase_order_template' => 'salesdock_purchase_order',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://graph.facebook.com/v27.0/12345/messages' => Http::response(['messages' => [['id' => 'wamid.test']]])]);

        $whatsapp = app(ZohoWhatsApp::class);
        $this->assertTrue($whatsapp->sendRestockAlert('08030000000', ['shop' => 'My Shop', 'stock' => '0']));
        $this->assertTrue($whatsapp->sendPurchaseOrder('08030000000', ['shop' => 'My Shop']));
        Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['to'] === '2348030000000'
            && $request['template']['name'] === 'salesdock_restock'
            && $request['template']['components'][0]['parameters'][1]['text'] === '0');
        Http::assertSent(fn (ClientRequest $request): bool => $request['template']['name'] === 'salesdock_purchase_order');
        Http::assertSentCount(2);
    }

    public function test_new_signup_only_sends_an_email_code_and_signs_in_after_confirmation(): void
    {
        config(['services.zeptomail.key' => 'fake-mail-key', 'services.zeptomail.from' => 'support@example.test',
            'services.meta_whatsapp.enabled' => true]);
        Http::preventStrayRequests();
        Http::fake(['https://api.zeptomail.com/v1.1/email' => Http::response(['message' => 'Accepted'])]);

        $this->post(route('register'), [
            'businessName' => 'Sample shop', 'email' => 'sample@example.test',
            'password' => 'Sample-password', 'password_confirmation' => 'Sample-password', 'channel' => 'whatsapp',
        ])->assertRedirect(route('register.verification'))->assertSessionHasNoErrors();

        $signup = TenantRegistration::query()->where('email', 'sample@example.test')->firstOrFail();
        $this->assertNull($signup->phone);
        $this->assertSame('email', $signup->verificationChannel);
        $this->assertNotNull($signup->verifyOtpHash);
        $mail = Http::recorded()->first()[0];
        preg_match('/<strong style="[^"]*font-size:32px[^"]*">(\d{6})<\/strong>/', $mail['htmlbody'], $matches);
        Http::assertSentCount(1);
        $this->get(route('register.verification'))->assertOk()->assertSee('spam folder');
        $this->post(route('register.verify-otp'), ['email' => $signup->email, 'otp' => $matches[1]])->assertRedirect();
        $this->assertTrue($signup->fresh()->emailVerified);
        $this->assertAuthenticated();
    }

    public function test_previously_issued_whatsapp_code_can_still_finish_signup(): void
    {
        Http::preventStrayRequests();
        $signup = $this->registration();
        $signup->update(['phone' => '+2348030000000', 'verificationChannel' => 'whatsapp', 'passwordHash' => Hash::make('Sample-password')]);
        $email = $signup->email;
        $this->withSession(['pending_registration' => $signup->id])
            ->get(route('register.verification'))->assertOk()->assertSee('number ending in 0000');
        $this->post(route('register.verify-otp'), ['email' => $email, 'otp' => '123456'])->assertRedirect();
        $this->assertTrue($signup->fresh()->accountVerified());
        $this->assertFalse($signup->fresh()->emailVerified);
        $this->assertAuthenticated();
        $this->app['auth']->guard()->logout();
        $this->post(route('password.email'), ['email' => $email])->assertSessionHas('status');
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Http::assertNothingSent();

        config(['services.zeptomail.key' => 'fake-mail-key', 'services.zeptomail.from' => 'support@example.test']);
        Http::fake(['https://api.zeptomail.com/v1.1/email' => Http::response(['message' => 'Accepted'])]);
        $this->post(route('login'), ['email' => $email, 'password' => 'Sample-password'])->assertRedirect();
        $this->assertAuthenticated();
        $this->post(route('recovery-email.send'))->assertSessionHasNoErrors();
        $mail = Http::recorded(fn (ClientRequest $request): bool => ($request->data()['subject'] ?? '') === 'Confirm your SalesDock recovery email')->first()[0];
        preg_match('/recovery-email\/confirm\?token=([a-zA-Z0-9]+)/', $mail['htmlbody'], $matches);
        $this->get(route('recovery-email.confirm', ['token' => $matches[1]]))->assertRedirect(route('profile'));
        $this->assertTrue($signup->fresh()->emailVerified);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.turnstile.site_key' => '', 'services.turnstile.secret' => '',
            'services.zeptomail.key' => '', 'services.zeptomail.from' => '']);
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $path): bool => ! str_contains($path, 'create_branch_stocks_table')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_login_and_password_reset_have_separate_verification_recovery_links(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Verify your account / resend code')->assertSee(route('register.verification'), false);
        $this->get(route('password.request'))->assertOk()->assertSee('A password reset does not verify your signup.');
        $this->get(route('register.verification'))->assertOk()->assertSee('Registered email')->assertSee('6-digit verification code')->assertSee('Signup password');
    }

    public function test_unverified_signup_login_redirects_to_verification_without_authenticating(): void
    {
        $registration = $this->registration();
        $this->post(route('login'), ['email' => strtoupper($registration->email), 'password' => 'Unchosen-password'])
            ->assertRedirect(route('register.verification'))->assertSessionHas('verification_email', $registration->email)->assertSessionHas('status');
        $this->assertGuest();
        $this->assertFalse($registration->fresh()->emailVerified);
        $this->get(route('register.verification'))->assertOk()->assertSee('Your signup is awaiting verification.');
    }

    public function test_existing_unverified_user_cannot_bypass_verification_with_a_correct_password(): void
    {
        $registration = $this->registration();
        $this->existingUser($registration, 'Known-password');
        $this->post(route('login'), ['email' => $registration->email, 'password' => 'Known-password'])
            ->assertRedirect(route('register.verification'));
        $this->assertGuest();
    }

    public function test_resend_rotates_verification_and_returns_to_the_code_entry_page(): void
    {
        Http::preventStrayRequests();
        $registration = $this->registration();
        $oldPassword = $registration->passwordHash;
        $this->post(route('register.resend'), ['email' => $registration->email])
            ->assertRedirect(route('register.verification'))->assertSessionHas('verification_email', $registration->email);
        $registration->refresh();
        $this->assertNotSame('verification-token', $registration->verifyToken);
        $this->assertNotNull($registration->verifyOtpHash);
        $this->assertSame($oldPassword, $registration->passwordHash);
        $this->assertFalse($registration->emailVerified);
        $this->assertSame(0, PasswordResetToken::query()->count());
        $this->get(route('register.verification'))->assertOk()->assertSee($registration->email);
        $this->post(route('register.verify-otp'), ['email' => $registration->email, 'otp' => '123456'])->assertRedirect(route('register.verification'))->assertSessionHasErrors('otp');
        $this->get(route('verify-email', ['token' => 'verification-token']))->assertOk()->assertSee('This verification link is not valid.');
        Http::assertNothingSent();
    }

    public function test_verified_and_unknown_emails_receive_the_same_generic_resend_response(): void
    {
        Http::preventStrayRequests();
        $registration = $this->registration();
        $registration->update(['emailVerified' => true]);
        $this->post(route('register.resend'), ['email' => $registration->email])->assertRedirect(route('register.verification'));
        $message = session('status');
        $this->post(route('register.resend'), ['email' => 'unknown@example.test'])->assertRedirect(route('register.verification'))->assertSessionHas('status', $message);
        $this->assertSame('verification-token', $registration->fresh()->verifyToken);
        Http::assertNothingSent();
    }

    #[TestWith(['999999', false])]
    #[TestWith(['123456', true])]
    public function test_wrong_or_expired_codes_return_to_recovery_without_verifying(string $code, bool $expired): void
    {
        $registration = $this->registration();
        if ($expired) {
            $registration->update(['verifyTokenExp' => now()->subMinute()]);
        }
        $this->post(route('register.verify-otp'), ['email' => $registration->email, 'otp' => $code])
            ->assertRedirect(route('register.verification'))->assertSessionHasErrors('otp');
        $this->assertFalse($registration->fresh()->emailVerified);
        $this->assertGuest();
    }

    public function test_a_valid_code_leads_to_initial_password_setup_and_verifies_only_when_completed(): void
    {
        Http::preventStrayRequests();
        $registration = $this->registration();
        $this->post(route('register.verify-otp'), ['email' => $registration->email, 'otp' => '123456'])
            ->assertRedirect(route('verify-email', ['token' => $registration->verifyToken]));
        $this->assertFalse($registration->fresh()->emailVerified);
        $this->get(route('verify-email', ['token' => $registration->verifyToken]))->assertOk()->assertSee('name="password"', false);
        $this->post(route('verify-email.store'), ['token' => $registration->verifyToken, 'password' => 'Verified-password', 'password_confirmation' => 'Verified-password'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($registration->fresh()->emailVerified);
        $this->assertNotNull($registration->fresh()->emailVerifiedAt);
        $user = User::query()->where('email', $registration->email)->firstOrFail();
        $this->assertTrue(Hash::check('Verified-password', $user->passwordHash));
        $this->assertAuthenticatedAs($user);
        Http::assertNothingSent();
    }

    public function test_password_reset_changes_password_but_does_not_verify_a_signup(): void
    {
        $registration = $this->registration();
        $user = $this->existingUser($registration, 'Old-password');
        PasswordResetToken::query()->create(['userId' => $user->id, 'token' => 'reset-only-token', 'expiresAt' => now()->addHour()]);
        $this->post(route('password.update'), ['token' => 'reset-only-token', 'password' => 'New-password', 'password_confirmation' => 'New-password'])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('New-password', $user->fresh()->passwordHash));
        $this->assertFalse($registration->fresh()->emailVerified);
        $this->assertNull($registration->fresh()->emailVerifiedAt);
        $this->post(route('login'), ['email' => $registration->email, 'password' => 'New-password'])->assertRedirect(route('register.verification'));
        $this->assertGuest();
    }

    public function test_reset_and_verification_tokens_cannot_be_used_for_each_other(): void
    {
        $registration = $this->registration();
        $user = $this->existingUser($registration, 'Old-password');
        PasswordResetToken::query()->create(['userId' => $user->id, 'token' => 'reset-only-token', 'expiresAt' => now()->addHour()]);
        $this->get(route('verify-email', ['token' => 'reset-only-token']))->assertOk()->assertSee('This verification link is not valid.');
        $this->post(route('password.update'), ['token' => $registration->verifyToken, 'password' => 'New-password', 'password_confirmation' => 'New-password'])->assertSessionHasErrors('token');
        $this->assertTrue(Hash::check('Old-password', $user->fresh()->passwordHash));
        $this->assertFalse($registration->fresh()->emailVerified);
    }

    public function test_verification_resend_is_rate_limited(): void
    {
        Http::preventStrayRequests();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson(route('register.resend'), ['email' => 'unknown@example.test'])->assertRedirect();
        }
        $this->postJson(route('register.resend'), ['email' => 'unknown@example.test'])->assertTooManyRequests();
        Http::assertNothingSent();
    }

    public function test_resend_sends_a_verification_email_instead_of_a_reset_email(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.zeptomail.com/v1.1/email' => Http::response(['message' => 'Accepted'])]);
        config(['services.zeptomail.key' => 'fake-mail-key', 'services.zeptomail.from' => 'support@example.test']);
        $registration = $this->registration();

        $this->post(route('register.resend'), ['email' => $registration->email])->assertRedirect(route('register.verification'));

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://api.zeptomail.com/v1.1/email'
            && $request['subject'] === 'Your SalesDock verification code'
            && $request['to'][0]['email_address']['address'] === $registration->email
            && str_contains($request['htmlbody'], 'within 10 minutes')
            && ! str_contains($request['htmlbody'], '/reset-password'));
        Http::assertSentCount(1);
        $this->assertSame(0, PasswordResetToken::query()->count());
    }

    public function test_a_verified_account_can_still_log_in_normally(): void
    {
        $registration = $this->registration();
        $registration->update(['emailVerified' => true, 'emailVerifiedAt' => now()]);
        $user = $this->existingUser($registration, 'Known-password');

        $this->post(route('login'), ['email' => $registration->email, 'password' => 'Known-password'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    private function existingUser(TenantRegistration $registration, string $password): User
    {
        $tenant = app(TenantProvisioner::class)->provision($registration);
        $user = User::query()->where('tenantId', $tenant->id)->where('email', $registration->email)->firstOrFail();
        $user->update(['passwordHash' => Hash::make($password)]);

        return $user;
    }

    private function registration(): TenantRegistration
    {
        return TenantRegistration::query()->create(['businessName' => 'Verification Shop', 'ownerName' => 'Owner', 'email' => 'owner@example.test',
            'passwordHash' => Hash::make('Unchosen-placeholder'), 'verifyToken' => 'verification-token', 'verifyTokenExp' => now()->addDay(),
            'verifyOtpHash' => Hash::make('123456'), 'phone' => null]);
    }
}
