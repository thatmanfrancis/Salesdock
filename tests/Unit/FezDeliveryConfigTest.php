<?php

namespace Tests\Unit;

use App\Models\DeliveryAccount;
use App\Models\PlatformSetting;
use App\Services\FezDelivery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FezDeliveryConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('platform_settings');
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
        });
    }

    public function test_global_delivery_settings_are_admin_managed(): void
    {
        PlatformSetting::writeDelivery([
            'enabled' => false,
            'sandbox_base_url' => 'https://apisandbox.fezdelivery.co/v1/',
            'production_base_url' => 'https://api.example.test/v1/',
            'portal_url' => 'https://portal.example.test',
            'support_url' => 'https://support.example.test',
        ]);

        $config = PlatformSetting::delivery();
        $this->assertFalse($config['enabled']);
        $this->assertSame('https://apisandbox.fezdelivery.co/v1', $config['sandbox_base_url']);
        $this->assertSame('https://api.example.test/v1', $config['production_base_url']);
        $this->assertSame('https://portal.example.test', $config['portal_url']);
    }

    public function test_fez_status_normalisation_protects_wallet_pending_orders(): void
    {
        $fez = app(FezDelivery::class);

        $this->assertSame('AWAITING_FEZ_PAYMENT', $fez->normalizeStatus('Pending Payment', 'unpaid'));
        $this->assertSame('AWAITING_FEZ_PAYMENT', $fez->normalizeStatus('Pending Pick-Up', 'unpaid'));
        $this->assertSame('PENDING_PICKUP', $fez->normalizeStatus('Pending Pick-Up', 'paid'));
        $this->assertSame('DELIVERED', $fez->normalizeStatus('Delivered', 'paid'));
        $this->assertCount(37, FezDelivery::states());
    }

    public function test_quote_uses_vat_inclusive_total(): void
    {
        Http::fake([
            'https://fez.test/v1/order/cost' => Http::response([
                'data' => ['cost' => 4500, 'vat' => 337.5, 'totalCost' => 4837.5],
            ]),
        ]);

        $account = new DeliveryAccount([
            'tenantId' => 'tenant-1',
            'environment' => 'sandbox',
            'baseUrl' => 'https://fez.test/v1',
            'userId' => 'merchant-user',
            'password' => 'secret',
            'secretKey' => 'org-secret',
            'authToken' => 'token',
            'tokenExpiresAt' => now()->addHour(),
            'enabled' => true,
        ]);

        $quote = app(FezDelivery::class)->quote($account, 'Lagos', 'Lagos', 2.5);

        $this->assertSame(4837.5, $quote['totalCost']);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://fez.test/v1/order/cost'
            && $request->hasHeader('secret-key', 'org-secret')
            && $request['state'] === 'Lagos'
            && (float) $request['weight'] === 2.5);
    }

    public function test_webhook_signature_accepts_hmac_candidate(): void
    {
        $account = new DeliveryAccount(['secretKey' => 'org-secret']);
        $signature = hash_hmac('sha256', 'FEZ123Delivered1720000000', 'org-secret');

        $this->assertTrue(app(FezDelivery::class)->webhookSignatureValid($account, 'FEZ123', 'Delivered', '1720000000', $signature));
        $this->assertFalse(app(FezDelivery::class)->webhookSignatureValid($account, 'FEZ123', 'Delivered', '1720000000', 'bad'));
    }
}
