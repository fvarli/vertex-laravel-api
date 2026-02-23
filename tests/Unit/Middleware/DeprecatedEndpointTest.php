<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\DeprecatedEndpoint;
use Illuminate\Http\Request;
use Tests\TestCase;

class DeprecatedEndpointTest extends TestCase
{
    private DeprecatedEndpoint $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new DeprecatedEndpoint;
    }

    public function test_sets_sunset_and_deprecation_headers(): void
    {
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, function () {
            return response('ok');
        }, '2026-06-01');

        $this->assertEquals('true', $response->headers->get('Deprecation'));
        $this->assertNotNull($response->headers->get('Sunset'));
        $this->assertStringContainsString('Jun 2026', $response->headers->get('Sunset'));
    }

    public function test_sets_link_header_when_replacement_provided(): void
    {
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, function () {
            return response('ok');
        }, '2026-06-01', '/v2/resource');

        $this->assertEquals('</v2/resource>; rel="successor-version"', $response->headers->get('Link'));
    }

    public function test_no_link_header_when_no_replacement(): void
    {
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, function () {
            return response('ok');
        }, '2026-06-01');

        $this->assertNull($response->headers->get('Link'));
    }
}
