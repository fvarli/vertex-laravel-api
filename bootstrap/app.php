<?php

use App\Http\Middleware\ApiLogMiddleware;
use App\Http\Middleware\CompressResponse;
use App\Http\Middleware\EnforceIdempotencyForAppointments;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureWorkspaceApproved;
use App\Http\Middleware\EnsureWorkspaceContext;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SetCacheHeaders;
use App\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            RequestIdMiddleware::class,
            ForceJsonResponse::class,
        ], append: [
            SecurityHeadersMiddleware::class,
            SetLocaleMiddleware::class,
            CompressResponse::class,
        ]);
        $middleware->throttleApi('api');
        $middleware->alias([
            'user.active' => EnsureUserIsActive::class,
            'api.log' => ApiLogMiddleware::class,
            'workspace.context' => EnsureWorkspaceContext::class,
            'workspace.approved' => EnsureWorkspaceApproved::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'idempotent.appointments' => EnforceIdempotencyForAppointments::class,
            'cache.headers' => SetCacheHeaders::class,
        ]);
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null;
            }
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $resolveLocale = function (Request $request): void {
            $locale = strtolower(substr($request->header('Accept-Language', 'en'), 0, 2));
            if (! in_array($locale, ['en', 'tr'], true)) {
                $locale = 'en';
            }
            app()->setLocale($locale);
        };

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) use ($resolveLocale) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $resolveLocale($request);
                Log::channel('apilog')->warning('Too many requests', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_id' => $request->user()?->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => __('api.too_many_requests'),
                    'request_id' => $request->attributes->get('request_id'),
                ], 429)->withHeaders([
                    'Retry-After' => $e->getHeaders()['Retry-After'] ?? 60,
                ]);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($resolveLocale) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $resolveLocale($request);
                Log::channel('apilog')->warning('Forbidden', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_id' => $request->user()?->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => __('api.forbidden'),
                    'request_id' => $request->attributes->get('request_id'),
                ], 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($resolveLocale) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $resolveLocale($request);
                Log::channel('apilog')->warning('Resource not found', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_id' => $request->user()?->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => __('api.not_found'),
                    'request_id' => $request->attributes->get('request_id'),
                ], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($resolveLocale) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $resolveLocale($request);
                Log::channel('apilog')->warning('Method not allowed', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_id' => $request->user()?->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => __('api.method_not_allowed'),
                    'request_id' => $request->attributes->get('request_id'),
                ], 405);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($resolveLocale) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $resolveLocale($request);
                Log::channel('apilog')->warning('Unauthenticated', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_id' => $request->user()?->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => __('api.unauthenticated'),
                    'request_id' => $request->attributes->get('request_id'),
                ], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($resolveLocale) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $resolveLocale($request);
                Log::channel('apilog')->debug('Validation failed', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_id' => $request->user()?->id,
                    'errors' => $e->errors(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => __('api.validation_failed'),
                    'errors' => $e->errors(),
                    'request_id' => $request->attributes->get('request_id'),
                ], 422);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($resolveLocale) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $resolveLocale($request);
                $statusCode = $e->getStatusCode();
                Log::channel('apilog')->warning('HTTP Exception', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_id' => $request->user()?->id,
                    'status_code' => $statusCode,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $statusCode >= 500 ? __('api.server_error') : __('api.error'),
                    'request_id' => $request->attributes->get('request_id'),
                ], $statusCode);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($resolveLocale) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $resolveLocale($request);
                Log::channel('apilog')->error('Server Error', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_id' => $request->user()?->id,
                    'exception' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? $e->getMessage() : __('api.server_error'),
                    'request_id' => $request->attributes->get('request_id'),
                ], 500);
            }
        });
    })->create();
