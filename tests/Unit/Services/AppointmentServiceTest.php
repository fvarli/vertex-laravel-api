<?php

namespace Tests\Unit\Services;

use App\Exceptions\AppointmentConflictException;
use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AppointmentReminderService;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private AppointmentService $service;

    private Workspace $workspace;

    private User $trainer;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AppointmentService::class);

        $owner = User::factory()->ownerAdmin()->create();
        $this->workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $this->trainer = User::factory()->trainer()->create();
        $this->workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $this->workspace->users()->attach($this->trainer->id, ['role' => 'trainer', 'is_active' => true]);

        $this->student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);
    }

    // ── Conflict Detection ─────────────────────────────────────

    public function test_create_detects_trainer_time_conflict(): void
    {
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $anotherStudent = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $this->expectException(AppointmentConflictException::class);

        $this->service->create($this->workspace->id, $this->trainer->id, $anotherStudent->id, [
            'starts_at' => '2026-06-10 10:30:00',
            'ends_at' => '2026-06-10 11:30:00',
        ]);
    }

    public function test_create_detects_student_time_conflict(): void
    {
        $anotherTrainer = User::factory()->trainer()->create();
        $this->workspace->users()->attach($anotherTrainer->id, ['role' => 'trainer', 'is_active' => true]);

        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $this->expectException(AppointmentConflictException::class);

        $this->service->create($this->workspace->id, $anotherTrainer->id, $this->student->id, [
            'starts_at' => '2026-06-10 10:30:00',
            'ends_at' => '2026-06-10 11:30:00',
        ]);
    }

    public function test_create_allows_adjacent_appointments(): void
    {
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $appointment = $this->service->create($this->workspace->id, $this->trainer->id, $this->student->id, [
            'starts_at' => '2026-06-10 11:00:00',
            'ends_at' => '2026-06-10 12:00:00',
        ]);

        $this->assertNotNull($appointment->id);
        $this->assertEquals(Appointment::STATUS_PLANNED, $appointment->status);
    }

    public function test_create_ignores_cancelled_appointments_for_conflicts(): void
    {
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        $appointment = $this->service->create($this->workspace->id, $this->trainer->id, $this->student->id, [
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
        ]);

        $this->assertNotNull($appointment->id);
    }

    public function test_update_detects_conflict_with_other_appointments(): void
    {
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 14:00:00',
            'ends_at' => '2026-06-10 15:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $this->expectException(AppointmentConflictException::class);

        $this->service->update($appointment, [
            'starts_at' => '2026-06-10 10:30:00',
            'ends_at' => '2026-06-10 11:30:00',
        ]);
    }

    public function test_update_ignores_self_for_conflict_detection(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $updated = $this->service->update($appointment, [
            'notes' => 'Updated notes',
        ]);

        $this->assertEquals('Updated notes', $updated->notes);
    }

    // ── Status Transitions ─────────────────────────────────────

    public function test_planned_to_done_allowed_for_past_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $updated = $this->service->updateStatus($appointment, Appointment::STATUS_DONE);

        $this->assertEquals(Appointment::STATUS_DONE, $updated->status);
    }

    public function test_planned_to_cancelled_allowed(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $updated = $this->service->updateStatus($appointment, Appointment::STATUS_CANCELLED);

        $this->assertEquals(Appointment::STATUS_CANCELLED, $updated->status);
    }

    public function test_planned_to_no_show_allowed_for_past_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $updated = $this->service->updateStatus($appointment, Appointment::STATUS_NO_SHOW);

        $this->assertEquals(Appointment::STATUS_NO_SHOW, $updated->status);
    }

    public function test_done_to_planned_allowed(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'status' => Appointment::STATUS_DONE,
        ]);

        $updated = $this->service->updateStatus($appointment, Appointment::STATUS_PLANNED);

        $this->assertEquals(Appointment::STATUS_PLANNED, $updated->status);
    }

    public function test_done_to_cancelled_not_allowed(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'status' => Appointment::STATUS_DONE,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->updateStatus($appointment, Appointment::STATUS_CANCELLED);
    }

    public function test_cancelled_to_planned_allowed(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        $updated = $this->service->updateStatus($appointment, Appointment::STATUS_PLANNED);

        $this->assertEquals(Appointment::STATUS_PLANNED, $updated->status);
    }

    public function test_cancelled_to_done_not_allowed(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->updateStatus($appointment, Appointment::STATUS_DONE);
    }

    public function test_future_appointment_cannot_be_marked_done(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->updateStatus($appointment, Appointment::STATUS_DONE);
    }

    public function test_future_appointment_cannot_be_marked_no_show(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->updateStatus($appointment, Appointment::STATUS_NO_SHOW);
    }

    public function test_same_status_transition_is_noop(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $updated = $this->service->updateStatus($appointment, Appointment::STATUS_PLANNED);

        $this->assertEquals(Appointment::STATUS_PLANNED, $updated->status);
    }

    public function test_no_show_to_planned_allowed(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'status' => Appointment::STATUS_NO_SHOW,
        ]);

        $updated = $this->service->updateStatus($appointment, Appointment::STATUS_PLANNED);

        $this->assertEquals(Appointment::STATUS_PLANNED, $updated->status);
    }

    // ── Workspace Scope ────────────────────────────────────────

    public function test_create_sets_correct_workspace_id(): void
    {
        $appointment = $this->service->create($this->workspace->id, $this->trainer->id, $this->student->id, [
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
        ]);

        $this->assertEquals($this->workspace->id, $appointment->workspace_id);
    }

    public function test_create_sets_default_planned_status(): void
    {
        $appointment = $this->service->create($this->workspace->id, $this->trainer->id, $this->student->id, [
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
        ]);

        $this->assertEquals(Appointment::STATUS_PLANNED, $appointment->status);
    }

    public function test_create_loads_student_and_trainer_relations(): void
    {
        $appointment = $this->service->create($this->workspace->id, $this->trainer->id, $this->student->id, [
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
        ]);

        $this->assertTrue($appointment->relationLoaded('student'));
        $this->assertTrue($appointment->relationLoaded('trainer'));
    }

    public function test_cancellation_triggers_reminder_cancellation(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $reminder = $appointment->reminders()->create([
            'workspace_id' => $this->workspace->id,
            'channel' => 'whatsapp',
            'scheduled_for' => now()->addHours(20),
            'status' => 'pending',
        ]);

        $this->service->updateStatus($appointment, Appointment::STATUS_CANCELLED);

        $this->assertEquals('cancelled', $reminder->fresh()->status);
    }
}
