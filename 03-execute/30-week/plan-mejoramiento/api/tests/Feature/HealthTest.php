<?php

namespace Tests\Feature;

use Tests\TestCase;

/** T-01: la API responde su salud sin token (CA-01.3). */
final class HealthTest extends TestCase
{
    public function test_health_responds_ok_without_token(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertExactJson(['status' => 'ok']);
    }

    public function test_old_laravel_health_route_does_not_exist(): void
    {
        $this->getJson('/up')->assertNotFound();
    }
}
