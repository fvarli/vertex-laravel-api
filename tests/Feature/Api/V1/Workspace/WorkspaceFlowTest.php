<?php

namespace Tests\Feature\Api\V1\Workspace;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_workspace_and_switch_context(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/v1/workspaces', [
            'name' => 'Coach Studio',
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Coach Studio');

        $workspaceId = (int) $createResponse->json('data.id');

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => 'owner_admin',
            'is_active' => 1,
        ]);

        $this->assertEquals($workspaceId, $user->fresh()->active_workspace_id);

        $listResponse = $this->getJson('/api/v1/me/workspaces');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $switchResponse = $this->postJson("/api/v1/workspaces/{$workspaceId}/switch");

        $switchResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_user_cannot_switch_workspace_without_membership(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $otherOwner->id]);
        $workspace->users()->attach($otherOwner->id, ['role' => 'owner_admin', 'is_active' => true]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/switch");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
