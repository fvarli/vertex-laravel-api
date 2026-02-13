<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiLogService
{
    private const FULL_MASK_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'token', 'secret', 'authorization', 'api_key',
    ];

    private const EMAIL_MASK_KEYS = ['email', 'user_email'];

    private const PHONE_MASK_KEYS = ['phone'];

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

        $context = $this->maskContext($this->buildContext($request, $extra));
        Log::channel('apilog')->{$level}($message, $context);
    }

    private function maskContext(array $context): array
    {
        $masked = [];

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = $this->maskContext($value);
                continue;
            }

            if (in_array($key, self::FULL_MASK_KEYS, true)) {
                $masked[$key] = '***';
            } elseif (in_array($key, self::EMAIL_MASK_KEYS, true) && is_string($value)) {
                $masked[$key] = $this->maskEmail($value);
            } elseif (in_array($key, self::PHONE_MASK_KEYS, true) && is_string($value)) {
                $masked[$key] = $this->maskPhone($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    private function maskEmail(string $value): string
    {
        $parts = explode('@', $value);
        if (count($parts) !== 2) {
            return '***';
        }

        [$local, $domain] = $parts;

        $maskedLocal = mb_substr($local, 0, min(2, mb_strlen($local))) . '***';

        $dotPos = strrpos($domain, '.');
        if ($dotPos === false) {
            return $maskedLocal . '@***';
        }

        $domainName = substr($domain, 0, $dotPos);
        $tld = substr($domain, $dotPos + 1);

        $maskedDomain = mb_substr($domainName, 0, min(2, mb_strlen($domainName))) . '***.' . $tld;

        return $maskedLocal . '@' . $maskedDomain;
    }

    private function maskPhone(string $value): string
    {
        $len = mb_strlen($value);
        if ($len <= 4) {
            return $value;
        }

        return str_repeat('*', $len - 4) . mb_substr($value, -4);
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
