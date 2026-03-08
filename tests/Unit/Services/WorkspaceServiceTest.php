<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkspaceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkspaceService::class);
    }

    public function test_switch_workspace_allows_platform_admin_without_membership(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);

        $this->service->switchWorkspace($admin, $workspace);

        $this->assertEquals($workspace->id, $admin->fresh()->active_workspace_id);
    }

    public function test_switch_workspace_throws_for_non_member(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);

        $this->expectException(AuthorizationException::class);

        $this->service->switchWorkspace($user, $workspace);
    }

    public function test_list_for_user_returns_all_workspaces_for_platform_admin(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        Workspace::factory()->count(3)->create(['owner_user_id' => User::factory()->create()->id]);

        $result = $this->service->listForUser($admin);

        $this->assertCount(3, $result);
    }

    public function test_list_for_user_returns_only_member_workspaces(): void
    {
        $user = User::factory()->create();
        $memberWorkspace = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);
        $memberWorkspace->users()->attach($user->id, ['role' => 'trainer', 'is_active' => true]);
        Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);

        $result = $this->service->listForUser($user);

        $this->assertCount(1, $result);
        $this->assertEquals($memberWorkspace->id, $result->first()->id);
    }
}
