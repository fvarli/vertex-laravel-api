<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';

    private const STATE_OPEN = 'open';

    private const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private readonly string $service,
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeout = 60,
        private readonly int $halfOpenMaxAttempts = 2,
    ) {}

    /**
     * Execute a callable through the circuit breaker.
     *
     * @return mixed Result of the callable, or the fallback value if circuit is open
     */
    public function call(callable $action, mixed $fallback = null): mixed
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptRecovery()) {
                $this->transitionTo(self::STATE_HALF_OPEN);
            } else {
                Log::warning("Circuit breaker OPEN for [{$this->service}], returning fallback");

                return $fallback;
            }
        }

        if ($state === self::STATE_HALF_OPEN && $this->getHalfOpenAttempts() >= $this->halfOpenMaxAttempts) {
            return $fallback;
        }

        try {
            $result = $action();

            $this->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();

            Log::warning("Circuit breaker failure for [{$this->service}]", [
                'error' => $e->getMessage(),
                'failures' => $this->getFailureCount(),
                'threshold' => $this->failureThreshold,
            ]);

            return $fallback;
        }
    }

    public function isAvailable(): bool
    {
        $state = $this->getState();

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN && $this->shouldAttemptRecovery()) {
            return true;
        }

        return $state === self::STATE_HALF_OPEN
            && $this->getHalfOpenAttempts() < $this->halfOpenMaxAttempts;
    }

    public function getState(): string
    {
        return Cache::get($this->key('state'), self::STATE_CLOSED);
    }

    private function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $this->transitionTo(self::STATE_CLOSED);
            Log::info("Circuit breaker CLOSED for [{$this->service}] — service recovered");
        }

        Cache::forget($this->key('failures'));
        Cache::forget($this->key('half_open_attempts'));
    }

    private function recordFailure(): void
    {
        $failures = Cache::increment($this->key('failures'));

        // Ensure TTL is set on the failures counter
        if ($failures === 1) {
            Cache::put($this->key('failures'), 1, $this->recoveryTimeout * 3);
        }

        if ($this->getState() === self::STATE_HALF_OPEN) {
            Cache::increment($this->key('half_open_attempts'));
            $this->transitionTo(self::STATE_OPEN);
            Log::warning("Circuit breaker re-OPENED for [{$this->service}] — half-open test failed");

            return;
        }

        if ($failures >= $this->failureThreshold) {
            $this->transitionTo(self::STATE_OPEN);
            Log::error("Circuit breaker OPENED for [{$this->service}] — {$failures} consecutive failures");
        }
    }

    private function shouldAttemptRecovery(): bool
    {
        $openedAt = Cache::get($this->key('opened_at'));

        return $openedAt && (time() - $openedAt) >= $this->recoveryTimeout;
    }

    private function transitionTo(string $state): void
    {
        Cache::put($this->key('state'), $state, $this->recoveryTimeout * 5);

        if ($state === self::STATE_OPEN) {
            Cache::put($this->key('opened_at'), time(), $this->recoveryTimeout * 5);
        }

        if ($state === self::STATE_CLOSED) {
            Cache::forget($this->key('failures'));
            Cache::forget($this->key('opened_at'));
            Cache::forget($this->key('half_open_attempts'));
        }

        if ($state === self::STATE_HALF_OPEN) {
            Cache::put($this->key('half_open_attempts'), 0, $this->recoveryTimeout * 5);
        }
    }

    private function getFailureCount(): int
    {
        return (int) Cache::get($this->key('failures'), 0);
    }

    private function getHalfOpenAttempts(): int
    {
        return (int) Cache::get($this->key('half_open_attempts'), 0);
    }

    private function key(string $suffix): string
    {
        return "circuit_breaker:{$this->service}:{$suffix}";
    }
}
