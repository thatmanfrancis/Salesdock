<?php

namespace Tests\Unit;

use App\Models\PlatformSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformPaymentConfigTest extends TestCase
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

    public function test_payment_falls_back_to_config_defaults(): void
    {
        config([
            'services.payments.provider' => 'flutterwave',
            'services.payments.platform_split' => 0.02,
            'services.flutterwave.public_key' => 'FLWPUBK_TEST-abc-X',
            'services.flutterwave.secret_key' => 'FLWSECK_TEST-abc-X',
            'services.flutterwave.secret_hash' => 'hash-one',
            'services.paystack.public_key' => '',
            'services.paystack.secret_key' => '',
        ]);

        $payment = PlatformSetting::payment();

        $this->assertSame('flutterwave', $payment['provider']);
        $this->assertSame(0.02, $payment['platform_split']);
        $this->assertSame('FLWPUBK_TEST-abc-X', $payment['flutterwave']['public_key']);
        $this->assertSame('FLWSECK_TEST-abc-X', $payment['flutterwave']['secret_key']);
    }

    public function test_write_payment_persists_provider_split_and_keeps_blank_secrets(): void
    {
        PlatformSetting::writePayment([
            'provider' => 'paystack',
            'platform_split' => 0.025,
            'flutterwave' => [
                'public_key' => 'FLWPUBK_TEST-keep-X',
                'secret_key' => 'FLWSECK_TEST-keep-X',
                'secret_hash' => 'secret-hash',
            ],
            'paystack' => [
                'public_key' => 'pk_test_abc',
                'secret_key' => 'sk_test_abc',
            ],
        ]);

        PlatformSetting::writePayment([
            'provider' => 'paystack',
            'platform_split' => 0.03,
            'flutterwave' => [
                'public_key' => 'FLWPUBK_TEST-new-X',
                'secret_key' => '',
                'secret_hash' => '',
            ],
            'paystack' => [
                'public_key' => 'pk_test_new',
                'secret_key' => '',
            ],
        ]);

        $payment = PlatformSetting::payment();

        $this->assertSame('paystack', $payment['provider']);
        $this->assertSame(0.03, $payment['platform_split']);
        $this->assertSame('FLWPUBK_TEST-new-X', $payment['flutterwave']['public_key']);
        $this->assertSame('FLWSECK_TEST-keep-X', $payment['flutterwave']['secret_key']);
        $this->assertSame('secret-hash', $payment['flutterwave']['secret_hash']);
        $this->assertSame('pk_test_new', $payment['paystack']['public_key']);
        $this->assertSame('sk_test_abc', $payment['paystack']['secret_key']);
    }
}
