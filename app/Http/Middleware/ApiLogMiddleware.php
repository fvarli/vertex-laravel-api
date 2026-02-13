<?php

namespace App\Http\Middleware;

use App\Services\ApiLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiLogMiddleware
{
    public function __construct(private readonly ApiLogService $apiLogService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->apiLogService->flush($request, [
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
        ]);

        return $response;
    }
}
