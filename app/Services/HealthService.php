<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class HealthService
{
    public function runChecks(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = ! in_array('fail', array_column($checks, 'status'), true);

        return [
            'checks' => $checks,
            'status' => $allHealthy ? 'ok' : 'degraded',
        ];
    }

    public function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    public function checkCache(): array
    {
        try {
            Cache::store()->put('health_check', true, 10);
            Cache::store()->forget('health_check');

            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    public function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            if ($connection === 'sync') {
                return ['status' => 'ok'];
            }

            Queue::connection($connection)->size();

            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }
}
