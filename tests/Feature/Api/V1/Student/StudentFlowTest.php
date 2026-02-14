<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_can_create_and_list_students(): void
    {
        $owner = User::factory()->create();
        $trainer = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($owner);

        $storeResponse = $this->postJson('/api/v1/students', [
            'full_name' => 'Ali Veli',
            'phone' => '+905551234567',
            'trainer_user_id' => $trainer->id,
            'notes' => 'MVP student',
        ]);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.trainer_user_id', $trainer->id);

        $listResponse = $this->getJson('/api/v1/students?status=active');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_workspace_context_is_required_for_students_endpoints(): void
    {
        $trainer = User::factory()->create();
        Sanctum::actingAs($trainer);

        $response = $this->getJson('/api/v1/students');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_trainer_cannot_view_other_trainers_student(): void
    {
        $trainerA = User::factory()->create();
        $trainerB = User::factory()->create();
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainerA->id, ['role' => 'trainer', 'is_active' => true]);
        $workspace->users()->attach($trainerB->id, ['role' => 'trainer', 'is_active' => true]);

        $trainerA->update(['active_workspace_id' => $workspace->id]);
        $trainerB->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerA->id,
        ]);

        Sanctum::actingAs($trainerB);

        $response = $this->getJson("/api/v1/students/{$student->id}");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
