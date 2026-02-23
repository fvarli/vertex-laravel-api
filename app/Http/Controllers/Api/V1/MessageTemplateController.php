<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\MessageTemplate\StoreMessageTemplateRequest;
use App\Http\Requests\Api\V1\MessageTemplate\UpdateMessageTemplateRequest;
use App\Http\Resources\Api\V1\MessageTemplateResource;
use App\Models\MessageTemplate;
use App\Services\MessageTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageTemplateController extends BaseController
{
    public function __construct(private readonly MessageTemplateService $messageTemplateService) {}

    public function index(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return $this->sendResponse(
            MessageTemplateResource::collection($this->messageTemplateService->list($workspaceId))
        );
    }

    public function store(StoreMessageTemplateRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');

        if ($workspaceRole !== WorkspaceRole::OwnerAdmin->value) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        $template = $this->messageTemplateService->create($workspaceId, $request->validated());

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

        if ($workspaceRole !== WorkspaceRole::OwnerAdmin->value) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        if ((int) $messageTemplate->workspace_id !== $workspaceId) {
            return $this->sendError(__('api.not_found'), [], 404);
        }

        $messageTemplate = $this->messageTemplateService->update($messageTemplate, $request->validated());

        return $this->sendResponse(
            new MessageTemplateResource($messageTemplate),
            __('api.message_template.updated'),
        );
    }

    public function destroy(Request $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');

        if ($workspaceRole !== WorkspaceRole::OwnerAdmin->value) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        if ((int) $messageTemplate->workspace_id !== $workspaceId) {
            return $this->sendError(__('api.not_found'), [], 404);
        }

        $this->messageTemplateService->delete($messageTemplate);

        return $this->sendResponse([], __('api.message_template.deleted'));
    }
}
