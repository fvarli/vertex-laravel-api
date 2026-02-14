<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    private const MAX_REQUEST_ID_LENGTH = 128;

    public function handle(Request $request, Closure $next): Response
    {
        $clientRequestId = $request->header('X-Request-Id');
        $requestId = $this->isValidRequestId($clientRequestId)
            ? $clientRequestId
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function isValidRequestId(?string $requestId): bool
    {
        if (! is_string($requestId) || $requestId === '') {
            return false;
        }

        if (strlen($requestId) > self::MAX_REQUEST_ID_LENGTH) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9._:-]+$/', $requestId);
    }
}
