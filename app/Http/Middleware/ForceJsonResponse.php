<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.api_strict_json_only', false) && ! $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => __('api.web.api_client_only'),
            ], 403);
        }

        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
