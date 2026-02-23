<?php

namespace App\Http\Middleware;

use App\Enums\ApprovalStatus;
use App\Enums\SystemRole;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->system_role === SystemRole::PlatformAdmin->value) {
            return $next($request);
        }

        $workspaceId = (int) $request->attributes->get('workspace_id');

        if (! $workspaceId) {
            return $next($request);
        }

        $workspace = Workspace::query()->find($workspaceId);
        if (! $workspace) {
            return $next($request);
        }

        if ($workspace->approval_status !== ApprovalStatus::Approved->value) {
            return response()->json([
                'success' => false,
                'message' => __('api.workspace.approval_required'),
                'data' => [
                    'workspace_id' => $workspace->id,
                    'approval_status' => $workspace->approval_status,
                ],
                'request_id' => $request->attributes->get('request_id'),
            ], 403);
        }

        return $next($request);
    }
}
