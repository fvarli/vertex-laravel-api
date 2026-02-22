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

class ReminderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_cancel_pending_reminder(): void
    {
        [$owner, $workspace, $student] = $this->seedContext();

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => now()->addHours(2),
            'status' => AppointmentReminder::STATUS_PENDING,
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/reminders/{$reminder->id}/cancel")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', AppointmentReminder::STATUS_CANCELLED);

        $this->assertDatabaseHas('appointment_reminders', [
            'id' => $reminder->id,
            'status' => AppointmentReminder::STATUS_CANCELLED,
        ]);
    }

    public function test_owner_can_open_pending_reminder(): void
    {
        [$owner, $workspace, $student] = $this->seedContext();

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => now()->addHours(2),
            'status' => AppointmentReminder::STATUS_PENDING,
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/reminders/{$reminder->id}/open")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', AppointmentReminder::STATUS_READY);

        $reminder->refresh();
        $this->assertNotNull($reminder->opened_at);
        $this->assertEquals(AppointmentReminder::STATUS_READY, $reminder->status);
    }

    public function test_owner_can_mark_sent_reminder(): void
    {
        [$owner, $workspace, $student] = $this->seedContext();

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => now()->addHours(2),
            'status' => AppointmentReminder::STATUS_PENDING,
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/reminders/{$reminder->id}/mark-sent")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', AppointmentReminder::STATUS_SENT);

        $reminder->refresh();
        $this->assertEquals(AppointmentReminder::STATUS_SENT, $reminder->status);
        $this->assertNotNull($reminder->marked_sent_at);
        $this->assertEquals($owner->id, $reminder->marked_sent_by_user_id);
    }

    public function test_list_reminders_with_status_filter(): void
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
            'scheduled_for' => now()->addHours(1),
            'status' => AppointmentReminder::STATUS_PENDING,
        ]);

        AppointmentReminder::factory()->create([
            'workspace_id' => $workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => now()->addHours(2),
            'status' => AppointmentReminder::STATUS_SENT,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reminders?status=pending');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals(AppointmentReminder::STATUS_PENDING, $data[0]['status']);
    }

    public function test_bulk_mark_sent_action(): void
    {
        [$owner, $workspace, $student] = $this->seedContext();

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $reminder1 = AppointmentReminder::factory()->create([
            'workspace_id' => $workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => now()->addHours(1),
            'status' => AppointmentReminder::STATUS_PENDING,
        ]);

        $reminder2 = AppointmentReminder::factory()->create([
            'workspace_id' => $workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => now()->addHours(2),
            'status' => AppointmentReminder::STATUS_READY,
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/reminders/bulk', [
            'ids' => [$reminder1->id, $reminder2->id],
            'action' => 'mark-sent',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.affected', 2);

        $this->assertDatabaseHas('appointment_reminders', [
            'id' => $reminder1->id,
            'status' => AppointmentReminder::STATUS_SENT,
        ]);

        $this->assertDatabaseHas('appointment_reminders', [
            'id' => $reminder2->id,
            'status' => AppointmentReminder::STATUS_SENT,
        ]);
    }

    public function test_user_without_workspace_context_gets_403(): void
    {
        $user = User::factory()->ownerAdmin()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reminders')
            ->assertForbidden()
            ->assertJsonPath('success', false);
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
