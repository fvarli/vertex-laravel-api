<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok_when_all_services_healthy(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'version' => 'v1',
                'message' => 'All systems operational.',
            ])
            ->assertJsonStructure([
                'status',
                'version',
                'checks' => ['database', 'cache', 'queue'],
                'message',
            ]);
    }

    public function test_health_endpoint_returns_checks_structure(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.cache.status', 'ok')
            ->assertJsonPath('checks.queue.status', 'ok');
    }

    public function test_health_endpoint_returns_degraded_when_cache_fails(): void
    {
        Cache::shouldReceive('store')->andThrow(new \RuntimeException('Cache connection failed'));

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.cache.status', 'fail');
    }
}
