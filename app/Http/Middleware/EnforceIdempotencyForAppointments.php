<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdempotencyForAppointments
{
    private const MAX_KEY_LENGTH = 128;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() !== 'POST') {
            return $next($request);
        }

        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            return $next($request);
        }

        if (! $this->isValidKey($key)) {
            return response()->json([
                'success' => false,
                'message' => __('api.validation_failed'),
                'errors' => [
                    'idempotency_key' => ['Invalid Idempotency-Key header.'],
                ],
                'request_id' => $request->attributes->get('request_id'),
            ], 422);
        }

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $userId = (int) ($request->user()?->id ?? 0);
        $payloadJson = json_encode($request->all());
        $requestHash = hash('sha256', $payloadJson ?: '{}');
        $now = Carbon::now();

        $record = IdempotencyKey::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->first();

        if ($record) {
            if ($record->request_hash !== $requestHash) {
                return response()->json([
                    'success' => false,
                    'message' => __('api.validation_failed'),
                    'errors' => [
                        'code' => ['idempotency_payload_mismatch'],
                    ],
                    'request_id' => $request->attributes->get('request_id'),
                ], 422);
            }

            return response()->json($record->response_body, $record->response_status);
        }

        /** @var JsonResponse $response */
        $response = $next($request);

        if ($response instanceof JsonResponse && $response->status() >= 200 && $response->status() < 300) {
            IdempotencyKey::query()->create([
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'response_status' => $response->status(),
                'response_body' => $response->getData(true),
                'expires_at' => Carbon::now()->addHours((int) config('idempotency.ttl_hours', 24)),
            ]);
        }

        return $response;
    }

    private function isValidKey(string $key): bool
    {
        if ($key === '' || strlen($key) > self::MAX_KEY_LENGTH) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9._:-]+$/', $key);
    }
}
