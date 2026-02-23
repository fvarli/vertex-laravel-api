<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeprecatedEndpoint
{
    /**
     * Mark an endpoint as deprecated with Sunset header.
     *
     * Usage: middleware('deprecated:2026-06-01') or middleware('deprecated:2026-06-01,/v2/resource')
     *
     * @param  string  $sunset  ISO 8601 date when the endpoint will be removed
     * @param  string|null  $link  URL/path of the replacement endpoint
     */
    public function handle(Request $request, Closure $next, string $sunset, ?string $link = null): Response
    {
        $response = $next($request);

        // RFC 8594 Sunset header
        $sunsetDate = date('D, d M Y H:i:s \G\M\T', strtotime($sunset));
        $response->headers->set('Sunset', $sunsetDate);

        // RFC 8594 Deprecation header
        $response->headers->set('Deprecation', 'true');

        if ($link) {
            $response->headers->set('Link', "<{$link}>; rel=\"successor-version\"");
        }

        return $response;
    }
}
