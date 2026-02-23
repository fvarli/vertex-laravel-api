<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\User\ListWorkspaceTrainerOverviewRequest;
use App\Http\Requests\Api\V1\User\StoreWorkspaceTrainerRequest;
use App\Http\Resources\WorkspaceTrainerResource;
use App\Services\WorkspaceTrainerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainerController extends BaseController
{
    public function __construct(private readonly WorkspaceTrainerService $workspaceTrainerService) {}

    public function overview(ListWorkspaceTrainerOverviewRequest $request): JsonResponse
    {
        $forbidden = $this->ensureOwnerAdmin($request);
        if ($forbidden instanceof JsonResponse) {
            return $forbidden;
        }

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $validated = $request->validated();

        $payload = $this->workspaceTrainerService->overview(
            workspaceId: $workspaceId,
            includeInactive: (bool) ($validated['include_inactive'] ?? false),
            search: isset($validated['search']) ? trim((string) $validated['search']) : null,
        );

        return $this->sendResponse([
            'trainers' => WorkspaceTrainerResource::collection(collect($payload['trainers'])),
            'summary' => $payload['summary'],
        ]);
    }

    public function store(StoreWorkspaceTrainerRequest $request): JsonResponse
    {
        $forbidden = $this->ensureOwnerAdmin($request);
        if ($forbidden instanceof JsonResponse) {
            return $forbidden;
        }

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $trainer = $this->workspaceTrainerService->create($workspaceId, $request->validated());

        return $this->sendResponse(new WorkspaceTrainerResource($trainer), __('api.trainer.created'), 201);
    }

    private function ensureOwnerAdmin(Request $request): ?JsonResponse
    {
        $workspaceRole = (string) $request->attributes->get('workspace_role');
        if ($workspaceRole !== WorkspaceRole::OwnerAdmin->value) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        return null;
    }
}
