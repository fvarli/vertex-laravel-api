<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Webhook\StoreWebhookRequest;
use App\Http\Requests\Api\V1\Webhook\UpdateWebhookRequest;
use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends BaseController
{
    public function __construct(private readonly WebhookService $webhookService) {}

    public function index(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return $this->sendResponse($this->webhookService->list($workspaceId));
    }

    public function store(StoreWebhookRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $webhook = $this->webhookService->create(
            $workspaceId,
            $request->validated('url'),
            $request->validated('events'),
        );

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
        $workspaceId = (int) $request->attributes->get('workspace_id');

        if ($webhook->workspace_id !== $workspaceId) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        $webhook = $this->webhookService->update($webhook, $request->validated());

        return $this->sendResponse([
            'id' => $webhook->id,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'is_active' => $webhook->is_active,
        ]);
    }

    public function destroy(Request $request, WebhookEndpoint $webhook): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        if ($webhook->workspace_id !== $workspaceId) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        $this->webhookService->delete($webhook);

        return $this->sendResponse([], __('api.deleted'));
    }

    public function availableEvents(): JsonResponse
    {
        return $this->sendResponse(WebhookService::EVENTS);
    }
}
