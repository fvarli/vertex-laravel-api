<?php

namespace Tests\Unit\Services;

use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_starts_in_closed_state(): void
    {
        $cb = new CircuitBreaker('test-service');

        $this->assertEquals('closed', $cb->getState());
        $this->assertTrue($cb->isAvailable());
    }

    public function test_successful_call_returns_result(): void
    {
        $cb = new CircuitBreaker('test-service');

        $result = $cb->call(fn () => 'success');

        $this->assertEquals('success', $result);
        $this->assertEquals('closed', $cb->getState());
    }

    public function test_opens_after_failure_threshold(): void
    {
        $cb = new CircuitBreaker('test-service', failureThreshold: 3);

        for ($i = 0; $i < 3; $i++) {
            $cb->call(fn () => throw new \RuntimeException('fail'), 'fallback');
        }

        $this->assertEquals('open', $cb->getState());
        $this->assertFalse($cb->isAvailable());
    }

    public function test_returns_fallback_when_open(): void
    {
        $cb = new CircuitBreaker('test-service', failureThreshold: 2, recoveryTimeout: 300);

        // Trip the breaker
        for ($i = 0; $i < 2; $i++) {
            $cb->call(fn () => throw new \RuntimeException('fail'));
        }

        $result = $cb->call(fn () => 'should-not-execute', 'fallback-value');

        $this->assertEquals('fallback-value', $result);
    }

    public function test_failure_below_threshold_stays_closed(): void
    {
        $cb = new CircuitBreaker('test-service', failureThreshold: 5);

        $cb->call(fn () => throw new \RuntimeException('fail'), null);
        $cb->call(fn () => throw new \RuntimeException('fail'), null);

        $this->assertEquals('closed', $cb->getState());
        $this->assertTrue($cb->isAvailable());
    }

    public function test_successful_call_resets_failure_count(): void
    {
        $cb = new CircuitBreaker('test-service', failureThreshold: 3);

        // Fail twice
        $cb->call(fn () => throw new \RuntimeException('fail'), null);
        $cb->call(fn () => throw new \RuntimeException('fail'), null);

        // Succeed — should reset
        $cb->call(fn () => 'ok');

        // Two more failures should NOT trip (counter was reset)
        $cb->call(fn () => throw new \RuntimeException('fail'), null);
        $cb->call(fn () => throw new \RuntimeException('fail'), null);

        $this->assertEquals('closed', $cb->getState());
    }

    public function test_recovers_after_timeout(): void
    {
        $cb = new CircuitBreaker('test-service', failureThreshold: 2, recoveryTimeout: 1);

        for ($i = 0; $i < 2; $i++) {
            $cb->call(fn () => throw new \RuntimeException('fail'));
        }

        $this->assertEquals('open', $cb->getState());

        // Simulate timeout passing
        Cache::put('circuit_breaker:test-service:opened_at', time() - 2, 300);

        $this->assertTrue($cb->isAvailable());
    }
}
