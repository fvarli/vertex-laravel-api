<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompressResponse
{
    private const MIN_COMPRESS_SIZE = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldCompress($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        $encoding = $this->preferredEncoding($request);

        if (! $encoding || strlen($content) < self::MIN_COMPRESS_SIZE) {
            return $response;
        }

        $compressed = gzencode($content, 5);

        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));
        $response->headers->remove('Transfer-Encoding');

        if (! $response->headers->has('Vary')) {
            $response->headers->set('Vary', 'Accept-Encoding');
        } else {
            $vary = $response->headers->get('Vary');
            if (stripos($vary, 'Accept-Encoding') === false) {
                $response->headers->set('Vary', $vary.', Accept-Encoding');
            }
        }

        return $response;
    }

    private function shouldCompress(Request $request, Response $response): bool
    {
        if (! $response->isSuccessful() && $response->getStatusCode() !== 304) {
            return false;
        }

        if ($response->headers->has('Content-Encoding')) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'json') || str_contains($contentType, 'text');
    }

    private function preferredEncoding(Request $request): ?string
    {
        $accept = $request->header('Accept-Encoding', '');

        if (str_contains($accept, 'gzip')) {
            return 'gzip';
        }

        return null;
    }
}
