<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;

class ForceJsonResponseTest extends TestCase
{
    public function test_strict_json_mode_blocks_non_json_api_requests(): void
    {
        config(['app.api_strict_json_only' => true]);

        $response = $this->get('/api/v1/health');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'API access only. Please use an API client.',
            ]);
    }

    public function test_strict_json_mode_allows_json_requests(): void
    {
        config(['app.api_strict_json_only' => true]);

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
    }

    public function test_non_strict_mode_allows_standard_requests(): void
    {
        config(['app.api_strict_json_only' => false]);

        $response = $this->get('/api/v1/health');

        $response->assertStatus(200);
    }
}
