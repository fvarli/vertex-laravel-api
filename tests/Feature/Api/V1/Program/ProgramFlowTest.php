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
}
