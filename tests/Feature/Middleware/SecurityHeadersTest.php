<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_present(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-site');
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none';");
    }

    public function test_hsts_header_not_present_in_debug_mode(): void
    {
        config(['app.debug' => true]);

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_header_present_in_production_mode(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
