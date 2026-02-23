<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Workspace\UpdateWorkspaceApprovalRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Services\WorkspaceApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceApprovalController extends BaseController
{
    public function __construct(private readonly WorkspaceApprovalService $workspaceApprovalService) {}

    public function pending(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $workspaces = $this->workspaceApprovalService->listPending($perPage);

        return $this->sendResponse(WorkspaceResource::collection($workspaces)->response()->getData(true));
    }

    public function update(UpdateWorkspaceApprovalRequest $request, Workspace $workspace): JsonResponse
    {
        $validated = $request->validated();
        $note = isset($validated['approval_note']) ? trim((string) $validated['approval_note']) : null;

        $workspace = $validated['approval_status'] === ApprovalStatus::Approved->value
            ? $this->workspaceApprovalService->approve($workspace, $request->user(), $note)
            : $this->workspaceApprovalService->reject($workspace, $request->user(), $note);

        return $this->sendResponse(new WorkspaceResource($workspace), __('api.workspace.approval_updated'));
    }
}
