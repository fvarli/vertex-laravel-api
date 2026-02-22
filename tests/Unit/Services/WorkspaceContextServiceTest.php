<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceContextServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkspaceContextService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WorkspaceContextService;
    }

    public function test_get_active_workspace_returns_workspace(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $result = $this->service->getActiveWorkspace($owner);

        $this->assertEquals($workspace->id, $result->id);
    }

    public function test_get_active_workspace_throws_when_no_active_workspace(): void
    {
        $user = User::factory()->ownerAdmin()->create(['active_workspace_id' => null]);

        $this->expectException(AuthorizationException::class);

        $this->service->getActiveWorkspace($user);
    }

    public function test_get_active_workspace_throws_when_workspace_not_found(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        // Delete the workspace so the ID reference becomes stale
        $workspace->delete();

        $this->expectException(AuthorizationException::class);

        $this->service->getActiveWorkspace($owner->refresh());
    }

    public function test_get_active_workspace_throws_when_membership_inactive(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => false]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $this->expectException(AuthorizationException::class);

        $this->service->getActiveWorkspace($owner);
    }

    public function test_get_role_returns_owner_admin(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);

        $this->assertEquals('owner_admin', $this->service->getRole($owner, $workspace->id));
    }

    public function test_get_role_returns_trainer(): void
    {
        $trainer = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);

        $this->assertEquals('trainer', $this->service->getRole($trainer, $workspace->id));
    }

    public function test_get_role_returns_null_when_no_membership(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);

        $this->assertNull($this->service->getRole($user, $workspace->id));
    }

    public function test_get_role_returns_null_when_membership_inactive(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);
        $workspace->users()->attach($user->id, ['role' => 'trainer', 'is_active' => false]);

        $this->assertNull($this->service->getRole($user, $workspace->id));
    }

    public function test_is_owner_admin_returns_true_for_owner_admin(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);

        $this->assertTrue($this->service->isOwnerAdmin($owner, $workspace->id));
    }

    public function test_is_owner_admin_returns_false_for_trainer(): void
    {
        $trainer = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);

        $this->assertFalse($this->service->isOwnerAdmin($trainer, $workspace->id));
    }
}
