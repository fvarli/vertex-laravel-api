<?php

namespace App\Http\Middleware;

use App\Services\WorkspaceContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceContext
{
    public function __construct(private readonly WorkspaceContextService $workspaceContextService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $workspace = $this->workspaceContextService->getActiveWorkspace($user);
        $role = $this->workspaceContextService->getRole($user, $workspace->id);

        $request->attributes->set('workspace_id', $workspace->id);
        $request->attributes->set('workspace_role', $role);

        return $next($request);
    }
}
