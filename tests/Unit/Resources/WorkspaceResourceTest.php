<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_resource_returns_owner_admin_role_for_platform_admin(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);

        $this->actingAs($admin);

        $resource = new WorkspaceResource($workspace);
        $data = $resource->toArray(request());

        $this->assertEquals('owner_admin', $data['role']);
    }

    public function test_workspace_resource_returns_null_role_for_regular_user_without_pivot(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);

        $this->actingAs($user);

        $resource = new WorkspaceResource($workspace);
        $data = $resource->toArray(request());

        $this->assertNull($data['role']);
    }

    public function test_workspace_resource_returns_pivot_role_when_present(): void
    {
        $trainer = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);

        $this->actingAs($trainer);

        $workspaceWithPivot = $trainer->workspaces()->find($workspace->id);
        $resource = new WorkspaceResource($workspaceWithPivot);
        $data = $resource->toArray(request());

        $this->assertEquals('trainer', $data['role']);
    }
}
