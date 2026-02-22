<?php

namespace Tests\Feature\Api\V1\Notification;

use App\Channels\FcmChannel;
use App\Models\Appointment;
use App\Models\DeviceToken;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\AppointmentCreatedNotification;
use App\Notifications\AppointmentReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $trainer;

    private Workspace $workspace;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->ownerAdmin()->create();
        $this->trainer = User::factory()->trainer()->create();
        $this->workspace = Workspace::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->workspace->users()->attach($this->owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $this->workspace->users()->attach($this->trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $this->trainer->update(['active_workspace_id' => $this->workspace->id]);

        $this->student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_ACTIVE,
        ]);
    }

    public function test_appointment_creation_sends_notification_to_trainer(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/v1/appointments', [
            'student_id' => $this->student->id,
            'starts_at' => '2026-07-10 10:00:00',
            'ends_at' => '2026-07-10 11:00:00',
        ])->assertStatus(201);

        Notification::assertSentTo($this->trainer, AppointmentCreatedNotification::class);
    }

    public function test_appointment_cancellation_sends_notification(): void
    {
        Notification::fake();

        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDay()->setHour(10),
            'ends_at' => now()->addDay()->setHour(11),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Sanctum::actingAs($this->trainer);

        $this->patchJson("/api/v1/appointments/{$appointment->id}/status", [
            'status' => 'cancelled',
        ])->assertOk();

        Notification::assertSentTo($this->trainer, AppointmentCancelledNotification::class);
    }

    public function test_non_cancellation_status_does_not_send_cancelled_notification(): void
    {
        Notification::fake();

        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Sanctum::actingAs($this->trainer);

        $this->patchJson("/api/v1/appointments/{$appointment->id}/status", [
            'status' => 'done',
        ])->assertOk();

        Notification::assertNotSentTo($this->trainer, AppointmentCancelledNotification::class);
    }

    public function test_created_notification_has_fcm_payload(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-07-10 10:00:00',
            'ends_at' => '2026-07-10 11:00:00',
        ]);
        $appointment->load('student');

        $notification = new AppointmentCreatedNotification($appointment);
        $payload = $notification->toFcm($this->trainer);

        $this->assertEquals('New appointment created', $payload['title']);
        $this->assertStringContainsString($this->student->full_name, $payload['body']);
        $this->assertEquals('appointment.created', $payload['data']['type']);
    }

    public function test_cancelled_notification_has_fcm_payload(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-07-10 10:00:00',
            'ends_at' => '2026-07-10 11:00:00',
        ]);
        $appointment->load('student');

        $notification = new AppointmentCancelledNotification($appointment);
        $payload = $notification->toFcm($this->trainer);

        $this->assertEquals('Appointment cancelled', $payload['title']);
        $this->assertEquals('appointment.cancelled', $payload['data']['type']);
    }

    public function test_reminder_notification_has_fcm_payload(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-07-10 10:00:00',
            'ends_at' => '2026-07-10 11:00:00',
        ]);
        $appointment->load('student');

        $notification = new AppointmentReminderNotification($appointment);
        $payload = $notification->toFcm($this->trainer);

        $this->assertEquals('Appointment reminder', $payload['title']);
        $this->assertEquals('appointment.reminder', $payload['data']['type']);
    }

    public function test_notification_includes_fcm_channel_when_device_exists(): void
    {
        DeviceToken::factory()->create(['user_id' => $this->trainer->id, 'is_active' => true]);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
        ]);

        $notification = new AppointmentCreatedNotification($appointment);
        $channels = $notification->via($this->trainer);

        $this->assertContains(FcmChannel::class, $channels);
    }

    public function test_notification_excludes_fcm_channel_without_device(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
        ]);

        $notification = new AppointmentCreatedNotification($appointment);
        $channels = $notification->via($this->trainer);

        $this->assertNotContains(FcmChannel::class, $channels);
    }
}
