<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Webhook\StoreWebhookRequest;
use App\Http\Requests\Api\V1\Webhook\UpdateWebhookRequest;
use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $workspaceId = $request->attributes->get('workspace_id');

        $webhooks = WebhookEndpoint::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'url' => $w->url,
                'events' => $w->events,
                'is_active' => $w->is_active,
                'failure_count' => $w->failure_count,
                'last_triggered_at' => $w->last_triggered_at?->toIso8601String(),
                'created_at' => $w->created_at->toIso8601String(),
            ]);

        return $this->sendResponse($webhooks);
    }

    public function store(StoreWebhookRequest $request): JsonResponse
    {
        $workspaceId = $request->attributes->get('workspace_id');

        $webhook = WebhookEndpoint::create([
            'workspace_id' => $workspaceId,
            'url' => $request->validated('url'),
            'events' => $request->validated('events'),
            'secret' => Str::random(48),
            'is_active' => true,
        ]);

        return $this->sendResponse([
            'id' => $webhook->id,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'secret' => $webhook->secret,
            'is_active' => $webhook->is_active,
            'created_at' => $webhook->created_at->toIso8601String(),
        ], __('api.created'), 201);
    }

    public function update(UpdateWebhookRequest $request, WebhookEndpoint $webhook): JsonResponse
    {
        $workspaceId = $request->attributes->get('workspace_id');

        if ($webhook->workspace_id !== (int) $workspaceId) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        $webhook->update($request->validated());

        return $this->sendResponse([
            'id' => $webhook->id,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'is_active' => $webhook->is_active,
        ]);
    }

    public function destroy(Request $request, WebhookEndpoint $webhook): JsonResponse
    {
        $workspaceId = $request->attributes->get('workspace_id');

        if ($webhook->workspace_id !== (int) $workspaceId) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        $webhook->delete();

        return $this->sendResponse([], __('api.deleted'));
    }

    public function availableEvents(): JsonResponse
    {
        return $this->sendResponse(WebhookService::EVENTS);
    }
}
