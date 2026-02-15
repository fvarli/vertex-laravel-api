<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\HealthService;
use Illuminate\Http\JsonResponse;

class HealthController
{
    public function __construct(
        private readonly HealthService $healthService,
    ) {}

    /**
     * Run health checks for database, cache and queue.
     */
    public function __invoke(): JsonResponse
    {
        $result = $this->healthService->runChecks();

        $message = $result['status'] === 'ok'
            ? __('api.health.ok')
            : __('api.health.degraded');

        return response()->json([
            'status'  => $result['status'],
            'version' => 'v1',
            'checks'  => $result['checks'],
            'message' => $message,
            'request_id' => request()->attributes->get('request_id'),
        ]);
    }
}
