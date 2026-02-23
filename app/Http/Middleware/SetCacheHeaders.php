<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCacheHeaders
{
    public function handle(Request $request, Closure $next, string $maxAge = '60'): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $etag = '"'.md5($response->getContent()).'"';

            if ($request->header('If-None-Match') === $etag) {
                return response('', 304)->withHeaders([
                    'ETag' => $etag,
                    'Cache-Control' => "private, max-age={$maxAge}",
                ]);
            }

            $response->headers->set('Cache-Control', "private, max-age={$maxAge}");
            $response->headers->set('ETag', $etag);
        }

        return $response;
    }
}
