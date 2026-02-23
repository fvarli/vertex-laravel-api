<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CompressResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class CompressResponseTest extends TestCase
{
    private CompressResponse $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CompressResponse;
    }

    public function test_compresses_json_response_when_client_accepts_gzip(): void
    {
        $body = json_encode(array_fill(0, 100, ['name' => 'Test Student', 'email' => 'test@example.com']));
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip, deflate');

        $response = $this->middleware->handle($request, function () use ($body) {
            return response($body, 200, ['Content-Type' => 'application/json']);
        });

        $this->assertEquals('gzip', $response->headers->get('Content-Encoding'));
        $this->assertLessThan(strlen($body), strlen($response->getContent()));
        $this->assertEquals($body, gzdecode($response->getContent()));
    }

    public function test_skips_compression_when_client_does_not_accept_gzip(): void
    {
        $body = json_encode(array_fill(0, 100, ['name' => 'Test']));
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, function () use ($body) {
            return response($body, 200, ['Content-Type' => 'application/json']);
        });

        $this->assertNull($response->headers->get('Content-Encoding'));
        $this->assertEquals($body, $response->getContent());
    }

    public function test_skips_compression_for_small_responses(): void
    {
        $body = '{"id":1}';
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $response = $this->middleware->handle($request, function () use ($body) {
            return response($body, 200, ['Content-Type' => 'application/json']);
        });

        $this->assertNull($response->headers->get('Content-Encoding'));
    }

    public function test_skips_compression_for_error_responses(): void
    {
        $body = str_repeat('x', 2000);
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $response = $this->middleware->handle($request, function () use ($body) {
            return response($body, 500, ['Content-Type' => 'application/json']);
        });

        $this->assertNull($response->headers->get('Content-Encoding'));
    }

    public function test_sets_vary_header(): void
    {
        $body = json_encode(array_fill(0, 100, ['name' => 'Test']));
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $response = $this->middleware->handle($request, function () use ($body) {
            return response($body, 200, ['Content-Type' => 'application/json']);
        });

        $this->assertStringContainsString('Accept-Encoding', $response->headers->get('Vary'));
    }

    public function test_appends_vary_when_already_exists(): void
    {
        $body = json_encode(array_fill(0, 100, ['name' => 'Test']));
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $response = $this->middleware->handle($request, function () use ($body) {
            return response($body, 200, ['Content-Type' => 'application/json', 'Vary' => 'Accept-Language']);
        });

        $this->assertEquals('Accept-Language, Accept-Encoding', $response->headers->get('Vary'));
    }
}
