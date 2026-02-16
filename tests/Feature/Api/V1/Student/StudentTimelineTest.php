<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\Appointment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_returns_program_and_appointment_events(): void
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
            'week_start_date' => '2026-03-03',
            'status' => Program::STATUS_ACTIVE,
            'title' => 'Week Program',
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $student->id,
            'trainer_user_id' => $owner->id,
            'starts_at' => '2026-03-04 09:00:00',
            'ends_at' => '2026-03-04 10:00:00',
            'status' => Appointment::STATUS_PLANNED,
            'location' => 'Gym A',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/v1/students/{$student->id}/timeline?limit=10");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.items.0.type', 'appointment')
            ->assertJsonPath('data.items.1.type', 'program');
    }
}
