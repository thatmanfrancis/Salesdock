<?php

namespace Tests\Unit;

use App\Models\PlatformSetting;
use App\Services\PaymentManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PaymentManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('platform_settings');
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
        });
    }

    public function test_active_driver_follows_platform_setting(): void
    {
        PlatformSetting::writePayment([
            'provider' => 'paystack',
            'platform_split' => 0.015,
            'flutterwave' => [],
            'paystack' => [],
        ]);

        $manager = app(PaymentManager::class);

        $this->assertSame('paystack', $manager->active()->name());
        $this->assertSame('Paystack', $manager->active()->label());
        $this->assertSame('paystackSubaccountCode', $manager->active()->subaccountColumn());
        $this->assertSame(0.015, $manager->platformSplit());
        $this->assertSame(1.5, $manager->platformSplitPercent());
    }

    public function test_driver_returns_flutterwave(): void
    {
        $manager = app(PaymentManager::class);

        $this->assertSame('flutterwave', $manager->driver('flutterwave')->name());
        $this->assertSame('flutterwaveSubaccountId', $manager->driver('flutterwave')->subaccountColumn());
    }

    #[TestWith(['flutterwave'])]
    #[TestWith(['paystack'])]
    public function test_saved_provider_is_used_when_both_providers_have_credentials(string $provider): void
    {
        config(['services.payments.provider' => $provider === 'paystack' ? 'flutterwave' : 'paystack']);
        PlatformSetting::writePayment([
            'provider' => $provider,
            'platform_split' => 0.015,
            'flutterwave' => ['secret_key' => 'FLWSECK_TEST-example-X'],
            'paystack' => ['secret_key' => 'sk_test_example'],
        ]);

        $manager = app(PaymentManager::class);

        $this->assertTrue($manager->driver('flutterwave')->ready());
        $this->assertTrue($manager->driver('paystack')->ready());
        $this->assertSame($provider, $manager->active()->name());
    }

    public function test_missing_selected_credentials_does_not_activate_the_other_provider(): void
    {
        config(['services.paystack.secret_key' => '']);
        PlatformSetting::writePayment([
            'provider' => 'paystack',
            'platform_split' => 0.015,
            'flutterwave' => ['secret_key' => 'FLWSECK_TEST-example-X'],
        ]);

        $manager = app(PaymentManager::class);

        $this->assertTrue($manager->driver('flutterwave')->ready());
        $this->assertSame('paystack', $manager->active()->name());
        $this->assertFalse($manager->active()->ready());
    }
}
