<?php

namespace Tests\Feature\Api\V1\Reminder;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReminderAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_can_requeue_and_bulk_cancel_reminders(): void
    {
        [$owner, $workspace, $student] = $this->seedContext();

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $failed = AppointmentReminder::factory()->create([
            'workspace_id' => $workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => now()->addMinutes(1),
            'status' => AppointmentReminder::STATUS_FAILED,
            'attempt_count' => 1,
            'next_retry_at' => now()->subMinutes(1),
            'failure_reason' => 'network_error',
        ]);

        $pending = AppointmentReminder::factory()->create([
            'workspace_id' => $workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => now()->addMinutes(2),
            'status' => AppointmentReminder::STATUS_PENDING,
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/reminders/{$failed->id}/requeue", [
            'failure_reason' => 'manual_retry',
        ])->assertOk()
            ->assertJsonPath('data.status', AppointmentReminder::STATUS_PENDING);

        $this->postJson('/api/v1/reminders/bulk', [
            'ids' => [$failed->id, $pending->id],
            'action' => 'cancel',
        ])->assertOk()
            ->assertJsonPath('data.affected', 2);

        $this->assertDatabaseHas('appointment_reminders', [
            'id' => $failed->id,
            'status' => AppointmentReminder::STATUS_CANCELLED,
        ]);
    }

    public function test_export_csv_returns_ok(): void
    {
        [$owner, $workspace, $student] = $this->seedContext();

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        AppointmentReminder::factory()->create([
            'workspace_id' => $workspace->id,
            'appointment_id' => $appointment->id,
            'status' => AppointmentReminder::STATUS_PENDING,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->get('/api/v1/reminders/export.csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    /**
     * @return array{0:User,1:Workspace,2:Student}
     */
    private function seedContext(): array
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
        ]);

        return [$owner, $workspace, $student];
    }
}
