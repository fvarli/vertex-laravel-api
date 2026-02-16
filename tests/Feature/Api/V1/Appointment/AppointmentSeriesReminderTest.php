<?php

namespace Tests\Feature\Api\V1\Appointment;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentSeriesReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_can_create_weekly_series_and_generate_reminders(): void
    {
        [$owner, $trainer, $workspace] = $this->seedWorkspace();

        $student = \App\Models\Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/appointments/series', [
            'student_id' => $student->id,
            'trainer_user_id' => $trainer->id,
            'title' => 'Morning PT',
            'location' => 'Studio A',
            'start_date' => now()->addDay()->toDateString(),
            'starts_at_time' => '09:00:00',
            'ends_at_time' => '10:00:00',
            'recurrence_rule' => [
                'freq' => 'weekly',
                'interval' => 1,
                'count' => 3,
                'byweekday' => [1, 3],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.generated_count', 3)
            ->assertJsonPath('data.skipped_conflicts_count', 0);

        $appointments = Appointment::query()->whereNotNull('series_id')->get();
        $this->assertCount(3, $appointments);

        $reminders = AppointmentReminder::query()->whereIn('appointment_id', $appointments->pluck('id'))->get();
        $this->assertCount(6, $reminders);
    }

    public function test_trainer_can_mark_reminder_sent(): void
    {
        [$owner, $trainer, $workspace] = $this->seedWorkspace();
        $student = \App\Models\Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
        ]);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $workspace->id,
            'appointment_id' => $appointment->id,
            'status' => AppointmentReminder::STATUS_PENDING,
            'scheduled_for' => now()->addHours(3),
        ]);

        Sanctum::actingAs($trainer);

        $openResponse = $this->patchJson("/api/v1/reminders/{$reminder->id}/open");
        $openResponse->assertOk()->assertJsonPath('data.status', AppointmentReminder::STATUS_READY);

        $markResponse = $this->patchJson("/api/v1/reminders/{$reminder->id}/mark-sent");
        $markResponse->assertOk()->assertJsonPath('data.status', AppointmentReminder::STATUS_SENT);

        $appointment->refresh();
        $this->assertSame(Appointment::WHATSAPP_STATUS_SENT, $appointment->whatsapp_status);
    }

    /**
     * @return array{0: User, 1: User, 2: Workspace}
     */
    private function seedWorkspace(): array
    {
        $owner = User::factory()->create();
        $trainer = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $owner->id,
            'reminder_policy' => ['whatsapp_offsets_minutes' => [1440, 120], 'enabled' => true],
        ]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);

        $owner->update(['active_workspace_id' => $workspace->id]);
        $trainer->update(['active_workspace_id' => $workspace->id]);

        return [$owner, $trainer, $workspace];
    }
}
