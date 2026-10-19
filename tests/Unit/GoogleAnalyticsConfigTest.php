<?php

namespace Tests\Unit;

use App\Models\PlatformSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleAnalyticsConfigTest extends TestCase
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

    public function test_google_analytics_id_accepts_ga4_and_ignores_invalid(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-ENVFALLBACK']);
        $this->assertSame('G-ENVFALLBACK', PlatformSetting::googleAnalyticsId());

        PlatformSetting::writeGoogleAnalytics('g-abc123xyz');
        $this->assertSame('G-ABC123XYZ', PlatformSetting::googleAnalyticsId());

        PlatformSetting::writeGoogleAnalytics('UA-12345-1');
        $this->assertSame('', PlatformSetting::googleAnalyticsId());
    }
}
