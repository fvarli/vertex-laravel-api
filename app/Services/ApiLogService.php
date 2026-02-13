<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiLogService
{
    private function buildContext(Request $request, array $extra = []): array
    {
        return array_merge([
            'ip'         => $request->ip(),
            'method'     => $request->method(),
            'url'        => $request->fullUrl(),
            'user_id'    => $request->user()?->id,
            'user_agent' => $request->userAgent(),
        ], $extra);
    }

    /**
     * Mesajı request üzerine kaydeder (hemen log yazmaz).
     * Middleware sonra flush() ile tek seferde yazar.
     */
    private function store(string $level, string $message, Request $request, array $extra = []): void
    {
        $request->attributes->set('api_log', [
            'level'   => $level,
            'message' => $message,
            'extra'   => $extra,
        ]);
    }

    /**
     * Middleware tarafından çağrılır. Kaydedilmiş business log + request/response
     * bilgilerini birleştirip TEK bir log satırı yazar.
     */
    public function flush(Request $request, array $responseData = []): void
    {
        $stored = $request->attributes->get('api_log');

        if ($stored) {
            $level   = $stored['level'];
            $message = $stored['message'];
            $extra   = array_merge($stored['extra'], $responseData);
        } else {
            $statusCode = $responseData['status_code'] ?? 200;
            $level = match (true) {
                $statusCode >= 500 => 'error',
                $statusCode >= 400 => 'warning',
                default            => 'info',
            };
            $message = 'API Request Completed';
            $extra   = $responseData;
        }

        Log::channel('apilog')->{$level}($message, $this->buildContext($request, $extra));
    }

    public function emergency(string $message, Request $request, array $extra = []): void
    {
        $this->store('emergency', $message, $request, $extra);
    }

    public function alert(string $message, Request $request, array $extra = []): void
    {
        $this->store('alert', $message, $request, $extra);
    }

    public function critical(string $message, Request $request, array $extra = []): void
    {
        $this->store('critical', $message, $request, $extra);
    }

    public function error(string $message, Request $request, array $extra = []): void
    {
        $this->store('error', $message, $request, $extra);
    }

    public function warning(string $message, Request $request, array $extra = []): void
    {
        $this->store('warning', $message, $request, $extra);
    }

    public function notice(string $message, Request $request, array $extra = []): void
    {
        $this->store('notice', $message, $request, $extra);
    }

    public function info(string $message, Request $request, array $extra = []): void
    {
        $this->store('info', $message, $request, $extra);
    }

    public function debug(string $message, Request $request, array $extra = []): void
    {
        $this->store('debug', $message, $request, $extra);
    }
}
