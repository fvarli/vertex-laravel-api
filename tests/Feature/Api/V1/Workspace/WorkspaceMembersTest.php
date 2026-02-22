<?php

namespace Tests\Feature\Api\V1\Workspace;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_workspace_members(): void
    {
        $owner = User::factory()->create();
        $trainer = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);

        $owner->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/members");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $memberIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($owner->id, $memberIds);
        $this->assertContains($trainer->id, $memberIds);

        $ownerData = collect($response->json('data'))->firstWhere('id', $owner->id);
        $this->assertEquals($owner->name, $ownerData['name']);
        $this->assertEquals($owner->surname, $ownerData['surname']);
        $this->assertEquals($owner->email, $ownerData['email']);
        $this->assertEquals('owner_admin', $ownerData['role']);
        $this->assertNotEmpty($ownerData['is_active']);
    }

    public function test_non_member_cannot_list_workspace_members(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);

        Sanctum::actingAs($outsider);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/members");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_owner_can_update_workspace_name(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}", [
            'name' => 'Updated Studio Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Studio Name');

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Updated Studio Name',
        ]);
    }

    public function test_trainer_cannot_update_workspace(): void
    {
        $owner = User::factory()->create();
        $trainer = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $trainer->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($trainer);

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}", [
            'name' => 'Trainer Attempted Update',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_workspace_validates_name(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}", [
            'name' => 'A',
        ]);

        $response->assertStatus(422);
    }
}
