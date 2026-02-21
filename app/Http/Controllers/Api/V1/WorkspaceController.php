<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Workspace\StoreWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends BaseController
{
    public function __construct(private readonly WorkspaceService $workspaceService) {}

    /**
     * List workspaces where authenticated user has active membership.
     */
    public function index(Request $request): JsonResponse
    {
        $workspaces = $request->user()
            ->workspaces()
            ->orderBy('workspaces.id')
            ->get();

        return $this->sendResponse(WorkspaceResource::collection($workspaces));
    }

    /**
     * Create a new workspace and assign requester as owner_admin.
     */
    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        $workspace = $this->workspaceService->createWorkspace($request->user(), $request->validated('name'));

        return $this->sendResponse(new WorkspaceResource($workspace), __('api.workspace.created_pending_approval'), 201);
    }

    /**
     * Switch authenticated user's active workspace.
     */
    public function switch(Request $request, Workspace $workspace): JsonResponse
    {
        $this->workspaceService->switchWorkspace($request->user(), $workspace);

        return $this->sendResponse([], __('api.workspace.switched'));
    }
}
