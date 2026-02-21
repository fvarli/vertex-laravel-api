<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->system_role !== 'platform_admin') {
            return response()->json([
                'success' => false,
                'message' => __('api.forbidden'),
                'request_id' => $request->attributes->get('request_id'),
            ], 403);
        }

        return $next($request);
    }
}
