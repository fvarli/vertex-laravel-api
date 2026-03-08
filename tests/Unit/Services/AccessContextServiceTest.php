<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Services\AccessContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessContextServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccessContextService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AccessContextService;
    }

    public function test_build_returns_owner_admin_role_for_platform_admin_with_active_workspace(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);
        $admin->update(['active_workspace_id' => $workspace->id]);

        $result = $this->service->build($admin);

        $this->assertEquals('platform_admin', $result['system_role']);
        $this->assertEquals('owner_admin', $result['active_workspace_role']);
        $this->assertEquals(['*'], $result['permissions']);
    }

    public function test_build_returns_null_role_for_platform_admin_without_active_workspace(): void
    {
        $admin = User::factory()->platformAdmin()->create(['active_workspace_id' => null]);

        $result = $this->service->build($admin);

        $this->assertEquals('platform_admin', $result['system_role']);
        $this->assertNull($result['active_workspace_role']);
        $this->assertEquals(['*'], $result['permissions']);
    }

    public function test_build_returns_correct_role_for_regular_user(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $result = $this->service->build($owner);

        $this->assertEquals('workspace_user', $result['system_role']);
        $this->assertEquals('owner_admin', $result['active_workspace_role']);
    }

    public function test_build_returns_null_role_for_regular_user_without_active_workspace(): void
    {
        $user = User::factory()->create(['active_workspace_id' => null]);

        $result = $this->service->build($user);

        $this->assertNull($result['active_workspace_role']);
    }
}
