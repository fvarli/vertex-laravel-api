<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Api\V1\Workspace\UpdateWorkspaceRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

class UpdateWorkspaceRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(User $user, Workspace $workspace): UpdateWorkspaceRequest
    {
        $request = UpdateWorkspaceRequest::create("/api/v1/workspaces/{$workspace->id}", 'PUT');
        $request->setUserResolver(fn () => $user);

        $route = new Route('PUT', 'api/v1/workspaces/{workspace}', []);
        $route->parameters = ['workspace' => $workspace];
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    public function test_platform_admin_can_update_any_workspace(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $request = $this->makeRequest($admin, $workspace);

        $this->assertTrue($request->authorize());
    }

    public function test_owner_can_update_own_workspace(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $request = $this->makeRequest($owner, $workspace);

        $this->assertTrue($request->authorize());
    }

    public function test_non_owner_cannot_update_workspace(): void
    {
        $otherUser = User::factory()->create();
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $request = $this->makeRequest($otherUser, $workspace);

        $this->assertFalse($request->authorize());
    }
}
