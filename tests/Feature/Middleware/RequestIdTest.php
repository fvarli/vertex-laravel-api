<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;

class RequestIdTest extends TestCase
{
    public function test_generates_uuid_when_no_request_id_header_sent(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertHeader('X-Request-Id');

        $requestId = $response->headers->get('X-Request-Id');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $requestId
        );
    }

    public function test_uses_client_request_id_when_provided(): void
    {
        $clientId = 'my-custom-request-id-123';

        $response = $this->getJson('/api/v1/health', [
            'X-Request-Id' => $clientId,
        ]);

        $response->assertStatus(200);
        $response->assertHeader('X-Request-Id', $clientId);
    }
}
