<?php

namespace Tests\Unit\Services;

use App\Services\ApiLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ApiLogServiceTest extends TestCase
{
    private ApiLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ApiLogService;
    }

    private function createRequest(array $extra = []): Request
    {
        $request = Request::create('/api/v1/test', 'POST');
        $request->attributes->set('api_log', array_merge([
            'level' => 'info',
            'message' => 'Test log',
            'extra' => [],
        ], $extra));

        return $request;
    }

    private function expectLogWithContext(callable $assertion, string $level = 'info'): void
    {
        $channel = \Mockery::mock();
        $channel->shouldReceive($level)
            ->once()
            ->withArgs(function (string $message, array $context) use ($assertion) {
                $assertion($context);

                return true;
            });

        Log::shouldReceive('channel')
            ->with('apilog')
            ->once()
            ->andReturn($channel);
    }

    public function test_masks_password_fields(): void
    {
        $request = $this->createRequest([
            'extra' => [
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'current_password' => 'oldsecret',
            ],
        ]);

        $this->expectLogWithContext(function (array $context) {
            $this->assertEquals('***', $context['password']);
            $this->assertEquals('***', $context['password_confirmation']);
            $this->assertEquals('***', $context['current_password']);
        });

        $this->service->flush($request);
    }

    public function test_masks_token_fields(): void
    {
        $request = $this->createRequest([
            'extra' => [
                'token' => 'abc123',
                'secret' => 'topsecret',
                'authorization' => 'Bearer xyz',
                'api_key' => 'key123',
            ],
        ]);

        $this->expectLogWithContext(function (array $context) {
            $this->assertEquals('***', $context['token']);
            $this->assertEquals('***', $context['secret']);
            $this->assertEquals('***', $context['authorization']);
            $this->assertEquals('***', $context['api_key']);
        });

        $this->service->flush($request);
    }

    public function test_masks_email_partially(): void
    {
        $request = $this->createRequest([
            'extra' => [
                'email' => 'test@example.com',
            ],
        ]);

        $this->expectLogWithContext(function (array $context) {
            $this->assertEquals('te***@ex***.com', $context['email']);
        });

        $this->service->flush($request);
    }

    public function test_masks_short_email(): void
    {
        $request = $this->createRequest([
            'extra' => [
                'email' => 'a@b.co',
            ],
        ]);

        $this->expectLogWithContext(function (array $context) {
            $this->assertEquals('a***@b***.co', $context['email']);
        });

        $this->service->flush($request);
    }

    public function test_masks_phone_number(): void
    {
        $request = $this->createRequest([
            'extra' => [
                'phone' => '+905551234567',
            ],
        ]);

        $this->expectLogWithContext(function (array $context) {
            $this->assertEquals('*********4567', $context['phone']);
        });

        $this->service->flush($request);
    }

    public function test_masks_short_phone(): void
    {
        $request = $this->createRequest([
            'extra' => [
                'phone' => '5551234567',
            ],
        ]);

        $this->expectLogWithContext(function (array $context) {
            $this->assertEquals('******4567', $context['phone']);
        });

        $this->service->flush($request);
    }

    public function test_leaves_non_sensitive_fields_unchanged(): void
    {
        $request = $this->createRequest([
            'extra' => [
                'status' => 'active',
                'count' => 42,
            ],
        ]);

        $this->expectLogWithContext(function (array $context) {
            $this->assertEquals('active', $context['status']);
            $this->assertEquals(42, $context['count']);
        });

        $this->service->flush($request);
    }

    public function test_masks_nested_arrays_recursively(): void
    {
        $request = $this->createRequest([
            'extra' => [
                'user' => [
                    'email' => 'nested@example.com',
                    'password' => 'secret',
                ],
            ],
        ]);

        $this->expectLogWithContext(function (array $context) {
            $this->assertEquals('ne***@ex***.com', $context['user']['email']);
            $this->assertEquals('***', $context['user']['password']);
        });

        $this->service->flush($request);
    }

    public function test_invalid_email_returns_triple_star(): void
    {
        $request = $this->createRequest([
            'extra' => [
                'email' => 'notanemail',
            ],
        ]);

        $this->expectLogWithContext(function (array $context) {
            $this->assertEquals('***', $context['email']);
        });

        $this->service->flush($request);
    }
}
