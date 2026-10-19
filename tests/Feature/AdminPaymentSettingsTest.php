<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminPaymentSettingsTest extends TestCase
{
    public function test_guests_cannot_update_payment_settings(): void
    {
        $this->post(route('admin.settings.payments'), [
            'provider' => 'paystack',
            'platform_split_percent' => 2,
        ])->assertRedirect('/login');
    }
}
