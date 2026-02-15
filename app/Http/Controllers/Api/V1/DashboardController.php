<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function summary(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');
        $trainerUserId = $workspaceRole === 'owner_admin' ? null : (int) $request->user()->id;

        $summary = $this->dashboardService->summary($workspaceId, $trainerUserId);

        return $this->sendResponse($summary);
    }
}
