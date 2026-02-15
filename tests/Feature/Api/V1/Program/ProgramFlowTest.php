<?php

namespace Tests\Feature\Api\V1\Program;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgramFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_create_second_active_program_for_same_student_week(): void
    {
        $trainer = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $trainer->id]);
        $workspace->users()->attach($trainer->id, ['role' => 'owner_admin', 'is_active' => true]);
        $trainer->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
        ]);

        Program::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $student->id,
            'trainer_user_id' => $trainer->id,
            'week_start_date' => '2026-02-16',
            'status' => Program::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($trainer);

        $response = $this->postJson("/api/v1/students/{$student->id}/programs", [
            'title' => 'Week Plan',
            'week_start_date' => '2026-02-16',
            'status' => Program::STATUS_ACTIVE,
            'items' => [
                [
                    'day_of_week' => 1,
                    'order_no' => 1,
                    'exercise' => 'Squat',
                    'sets' => 4,
                    'reps' => 8,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cannot_create_program_with_duplicate_day_and_order_items(): void
    {
        $trainer = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $trainer->id]);
        $workspace->users()->attach($trainer->id, ['role' => 'owner_admin', 'is_active' => true]);
        $trainer->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
        ]);

        Sanctum::actingAs($trainer);

        $response = $this->postJson("/api/v1/students/{$student->id}/programs", [
            'title' => 'Week Plan',
            'week_start_date' => '2026-02-16',
            'status' => Program::STATUS_DRAFT,
            'items' => [
                [
                    'day_of_week' => 1,
                    'order_no' => 1,
                    'exercise' => 'Squat',
                ],
                [
                    'day_of_week' => 1,
                    'order_no' => 1,
                    'exercise' => 'Bench Press',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['items.1.order_no']);
    }

    public function test_trainer_cannot_create_program_for_another_trainers_student(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainerA = User::factory()->trainer()->create();
        $trainerB = User::factory()->trainer()->create();

        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainerA->id, ['role' => 'trainer', 'is_active' => true]);
        $workspace->users()->attach($trainerB->id, ['role' => 'trainer', 'is_active' => true]);
        $trainerA->update(['active_workspace_id' => $workspace->id]);

        $studentForTrainerB = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerB->id,
        ]);

        Sanctum::actingAs($trainerA);

        $response = $this->postJson("/api/v1/students/{$studentForTrainerB->id}/programs", [
            'title' => 'Unauthorized Plan',
            'week_start_date' => '2026-02-16',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_programs_index_supports_search_sort_direction_and_status_contract(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
        ]);

        Program::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $student->id,
            'trainer_user_id' => $owner->id,
            'title' => 'Mobility Week',
            'status' => Program::STATUS_DRAFT,
        ]);

        $target = Program::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $student->id,
            'trainer_user_id' => $owner->id,
            'title' => 'Strength Week',
            'status' => Program::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/v1/students/{$student->id}/programs?search=strength&status=active&sort=title&direction=asc");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.id', $target->id)
            ->assertJsonPath('meta.total', 1);
    }
}
