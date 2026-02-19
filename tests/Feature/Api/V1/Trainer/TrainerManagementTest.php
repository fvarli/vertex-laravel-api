<?php

namespace Tests\Feature\Api\V1\Trainer;

use App\Models\Appointment;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_can_get_trainer_overview_and_create_trainer(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainerA = User::factory()->trainer()->create();
        $trainerB = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainerA->id, ['role' => 'trainer', 'is_active' => true]);
        $workspace->users()->attach($trainerB->id, ['role' => 'trainer', 'is_active' => false]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $trainerRole = Role::query()->where('name', 'trainer')->first();
        if ($trainerRole) {
            $trainerA->roles()->syncWithoutDetaching([$trainerRole->id => ['workspace_id' => $workspace->id]]);
            $trainerB->roles()->syncWithoutDetaching([$trainerRole->id => ['workspace_id' => $workspace->id]]);
        }

        $studentA = Student::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerA->id,
        ])->first();

        Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerB->id,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerA->id,
            'student_id' => $studentA->id,
            'starts_at' => now()->startOfDay()->addHours(2),
            'ends_at' => now()->startOfDay()->addHours(3),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Sanctum::actingAs($owner);

        $overviewResponse = $this->getJson('/api/v1/trainers/overview');

        $overviewResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_trainers', 1)
            ->assertJsonPath('data.summary.active_trainers', 1)
            ->assertJsonPath('data.summary.total_students', 2)
            ->assertJsonPath('data.trainers.0.id', $trainerA->id)
            ->assertJsonPath('data.trainers.0.student_count', 2);

        $overviewWithInactiveResponse = $this->getJson('/api/v1/trainers/overview?include_inactive=1');
        $overviewWithInactiveResponse->assertOk()
            ->assertJsonPath('data.summary.total_trainers', 2)
            ->assertJsonPath('data.summary.total_students', 3);

        $createResponse = $this->postJson('/api/v1/trainers', [
            'name' => 'Selin',
            'surname' => 'Acar',
            'email' => 'selin.acar@vertex.local',
            'phone' => '+905551234567',
            'password' => 'password123',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'selin.acar@vertex.local');

        $trainerId = (int) $createResponse->json('data.id');

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $trainerId,
            'role' => 'trainer',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $trainerId,
            'active_workspace_id' => $workspace->id,
            'system_role' => 'workspace_user',
        ]);
    }

    public function test_trainer_cannot_access_owner_admin_trainer_management_endpoints(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainer = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $trainer->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($trainer);

        $this->getJson('/api/v1/trainers/overview')
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->postJson('/api/v1/trainers', [
            'name' => 'Unauthorized',
            'surname' => 'Trainer',
            'email' => 'unauth.trainer@vertex.local',
            'password' => 'password123',
        ])->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
