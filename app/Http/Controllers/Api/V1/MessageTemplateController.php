<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\MessageTemplate\StoreMessageTemplateRequest;
use App\Http\Requests\Api\V1\MessageTemplate\UpdateMessageTemplateRequest;
use App\Http\Resources\Api\V1\MessageTemplateResource;
use App\Models\MessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageTemplateController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $templates = MessageTemplate::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return $this->sendResponse(MessageTemplateResource::collection($templates));
    }

    public function store(StoreMessageTemplateRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');

        if ($workspaceRole !== 'owner_admin') {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        $validated = $request->validated();

        if (! empty($validated['is_default'])) {
            MessageTemplate::query()
                ->where('workspace_id', $workspaceId)
                ->where('channel', $validated['channel'] ?? 'whatsapp')
                ->update(['is_default' => false]);
        }

        $template = MessageTemplate::query()->create([
            'workspace_id' => $workspaceId,
            'name' => $validated['name'],
            'channel' => $validated['channel'] ?? 'whatsapp',
            'body' => $validated['body'],
            'is_default' => $validated['is_default'] ?? false,
        ]);

        return $this->sendResponse(
            new MessageTemplateResource($template),
            __('api.message_template.created'),
            201,
        );
    }

    public function update(UpdateMessageTemplateRequest $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');

        if ($workspaceRole !== 'owner_admin') {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        if ((int) $messageTemplate->workspace_id !== $workspaceId) {
            return $this->sendError(__('api.not_found'), [], 404);
        }

        $validated = $request->validated();

        if (! empty($validated['is_default'])) {
            MessageTemplate::query()
                ->where('workspace_id', $workspaceId)
                ->where('channel', $messageTemplate->channel)
                ->where('id', '!=', $messageTemplate->id)
                ->update(['is_default' => false]);
        }

        $messageTemplate->update($validated);

        return $this->sendResponse(
            new MessageTemplateResource($messageTemplate),
            __('api.message_template.updated'),
        );
    }

    public function destroy(Request $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');

        if ($workspaceRole !== 'owner_admin') {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        if ((int) $messageTemplate->workspace_id !== $workspaceId) {
            return $this->sendError(__('api.not_found'), [], 404);
        }

        $messageTemplate->delete();

        return $this->sendResponse([], __('api.message_template.deleted'));
    }
}
