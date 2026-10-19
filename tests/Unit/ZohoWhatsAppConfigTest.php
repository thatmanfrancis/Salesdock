<?php

namespace Tests\Unit;

use App\Models\PlatformSetting;
use App\Support\PhoneNumber;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ZohoWhatsAppConfigTest extends TestCase
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

    public function test_write_zoho_settings_keeps_blank_secrets(): void
    {
        PlatformSetting::writeZohoWhatsApp([
            'enabled' => true,
            'client_id' => 'cid',
            'client_secret' => 'secret-one',
            'refresh_token' => 'refresh-one',
            'screen_name' => 'salesdock',
            'app_id' => '1',
            'department_id' => '2',
            'otp_template_id' => 'otp',
            'restock_template_id' => 'restock',
            'purchase_order_template_id' => '',
            'salesiq_widget_code' => '',
        ]);

        PlatformSetting::writeZohoWhatsApp([
            'enabled' => true,
            'client_id' => 'cid-2',
            'client_secret' => '',
            'refresh_token' => '',
            'screen_name' => 'salesdock',
            'app_id' => '1',
            'department_id' => '2',
            'otp_template_id' => 'otp-2',
            'restock_template_id' => 'restock',
            'purchase_order_template_id' => 'po',
            'salesiq_widget_code' => '<script>/* widget */</script>',
        ]);

        $cfg = PlatformSetting::zohoWhatsApp();
        $this->assertTrue($cfg['enabled']);
        $this->assertSame('cid-2', $cfg['client_id']);
        $this->assertSame('secret-one', $cfg['client_secret']);
        $this->assertSame('refresh-one', $cfg['refresh_token']);
        $this->assertSame('otp-2', $cfg['otp_template_id']);
        $this->assertSame('po', $cfg['purchase_order_template_id']);
        $this->assertSame('<script>/* widget */</script>', $cfg['salesiq_widget_code']);
    }

    public function test_phone_number_normalises_nigerian_mobiles(): void
    {
        $this->assertSame('+2348030000000', PhoneNumber::e164('0803 000 0000'));
        $this->assertSame('+2348030000000', PhoneNumber::e164('+2348030000000'));
        $this->assertSame('2348030000000', PhoneNumber::waMe('08030000000'));
        $this->assertNull(PhoneNumber::e164('123'));
    }
}
