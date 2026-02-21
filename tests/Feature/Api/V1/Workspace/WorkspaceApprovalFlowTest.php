<?php

namespace Tests\Feature\Api\V1\Workspace;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_workspace_blocks_critical_actions_until_platform_admin_approval(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        Sanctum::actingAs($owner);

        $createResponse = $this->postJson('/api/v1/workspaces', [
            'name' => 'Pending Studio',
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.approval_status', 'pending');

        $workspaceId = (int) $createResponse->json('data.id');

        $blockedResponse = $this->postJson('/api/v1/trainers', [
            'name' => 'New',
            'surname' => 'Trainer',
            'email' => 'new.trainer@vertex.local',
            'phone' => '+905551110001',
            'password' => 'password123',
        ]);

        $blockedResponse->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.workspace_id', $workspaceId)
            ->assertJsonPath('data.approval_status', 'pending');

        $platformAdmin = User::factory()->platformAdmin()->create();
        Sanctum::actingAs($platformAdmin);

        $this->getJson('/api/v1/platform/workspaces/pending')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->patchJson("/api/v1/platform/workspaces/{$workspaceId}/approval", [
            'approval_status' => 'approved',
            'approval_note' => 'Looks good.',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.approval_status', 'approved');

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspaceId,
            'approval_status' => 'approved',
            'approved_by_user_id' => $platformAdmin->id,
        ]);

        Sanctum::actingAs($owner->fresh());

        $allowedResponse = $this->postJson('/api/v1/trainers', [
            'name' => 'Allowed',
            'surname' => 'Trainer',
            'email' => 'allowed.trainer@vertex.local',
            'phone' => '+905551110002',
            'password' => 'password123',
        ]);

        $allowedResponse->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_non_platform_admin_cannot_use_workspace_approval_endpoints(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/platform/workspaces/pending')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
