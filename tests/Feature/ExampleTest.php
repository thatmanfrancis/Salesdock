<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_23_000001_create_salesdock_schema.php',
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
