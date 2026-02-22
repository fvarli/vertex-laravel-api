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

class ReportsFilteringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed a workspace with owner, trainer and one student.
     *
     * @return array{0: User, 1: Workspace, 2: User, 3: Student}
     */
    private function seedContext(): array
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
        ]);

        return [$owner, $workspace, $trainer, $student];
    }

    public function test_appointments_report_supports_week_grouping(): void
    {
        [$owner, $workspace, $trainer, $student] = $this->seedContext();

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/appointments?date_from=2026-06-01&date_to=2026-06-30&group_by=week');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.filters.group_by', 'week')
            ->assertJsonStructure([
                'data' => [
                    'totals',
                    'buckets',
                    'filters',
                ],
            ]);
    }

    public function test_students_report_supports_month_grouping(): void
    {
        [$owner, $workspace, $trainer] = $this->seedContext();

        Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'status' => Student::STATUS_ACTIVE,
            'created_at' => '2026-06-05 10:00:00',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/students?date_from=2026-06-01&date_to=2026-06-30&group_by=month');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.filters.group_by', 'month')
            ->assertJsonStructure([
                'data' => [
                    'totals',
                    'buckets',
                    'filters',
                ],
            ]);
    }

    public function test_programs_report_returns_correct_totals(): void
    {
        [$owner, $workspace, $trainer, $student] = $this->seedContext();

        Program::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $student->id,
            'trainer_user_id' => $trainer->id,
            'week_start_date' => '2026-06-02',
            'status' => Program::STATUS_ACTIVE,
        ]);

        Program::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $student->id,
            'trainer_user_id' => $trainer->id,
            'week_start_date' => '2026-06-09',
            'status' => Program::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/programs?date_from=2026-06-01&date_to=2026-06-30&group_by=day');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.total', 2)
            ->assertJsonPath('data.totals.active', 1)
            ->assertJsonPath('data.filters.group_by', 'day');
    }

    public function test_reminders_report_returns_correct_totals(): void
    {
        [$owner, $workspace, $trainer, $student] = $this->seedContext();

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
        ]);

        AppointmentReminder::factory()->create([
            'workspace_id' => $workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => '2026-06-10 08:00:00',
            'status' => AppointmentReminder::STATUS_SENT,
            'marked_sent_at' => '2026-06-10 07:59:00',
            'attempt_count' => 1,
        ]);

        AppointmentReminder::factory()->create([
            'workspace_id' => $workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => '2026-06-10 09:00:00',
            'status' => AppointmentReminder::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/reminders?date_from=2026-06-01&date_to=2026-06-30&group_by=week');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.total', 2)
            ->assertJsonPath('data.filters.group_by', 'week');
    }

    public function test_reports_require_workspace_context(): void
    {
        $user = User::factory()->ownerAdmin()->create();
        // Do NOT set active_workspace_id so the middleware rejects the request.

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/appointments');

        $response->assertStatus(403);
    }
}
