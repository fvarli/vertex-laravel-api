<?php

namespace Tests\Feature\Api\V1\Report;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsTrainerPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_can_view_trainer_performance(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainer = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'status' => Student::STATUS_ACTIVE,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-06-11 10:00:00',
            'ends_at' => '2026-06-11 11:00:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        Program::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $student->id,
            'trainer_user_id' => $trainer->id,
            'status' => Program::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/trainer-performance?date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.trainers.0.total_students', 1)
            ->assertJsonPath('data.trainers.0.active_students', 1)
            ->assertJsonPath('data.trainers.0.total_appointments', 2)
            ->assertJsonPath('data.trainers.0.completed_appointments', 1)
            ->assertJsonPath('data.trainers.0.cancellation_count', 1)
            ->assertJsonPath('data.trainers.0.completion_rate', 50)
            ->assertJsonPath('data.trainers.0.active_programs', 1);
    }

    public function test_trainer_cannot_access_trainer_performance(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainer = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $trainer->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($trainer);

        $response = $this->getJson('/api/v1/reports/trainer-performance?date_from=2026-06-01&date_to=2026-06-30');

        $response->assertStatus(403);
    }

    public function test_returns_multiple_trainers(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainerA = User::factory()->trainer()->create();
        $trainerB = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainerA->id, ['role' => 'trainer', 'is_active' => true]);
        $workspace->users()->attach($trainerB->id, ['role' => 'trainer', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/trainer-performance?date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk();
        $trainers = $response->json('data.trainers');
        $this->assertGreaterThanOrEqual(2, count($trainers));
    }
}
