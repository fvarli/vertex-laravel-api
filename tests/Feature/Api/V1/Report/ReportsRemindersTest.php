<?php

namespace Tests\Feature\Api\V1\Report;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_report_returns_totals_and_rates(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
        ]);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
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
            'status' => AppointmentReminder::STATUS_MISSED,
            'attempt_count' => 2,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/reminders?date_from=2026-06-01&date_to=2026-06-30&group_by=day');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.total', 2)
            ->assertJsonPath('data.totals.sent', 1)
            ->assertJsonPath('data.totals.missed', 1)
            ->assertJsonPath('data.totals.send_rate', 50);
    }
}
