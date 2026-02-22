<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    private Workspace $workspace;

    private User $trainer;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DashboardService;

        $owner = User::factory()->ownerAdmin()->create();
        $this->workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $this->trainer = User::factory()->trainer()->create();
        $this->workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $this->workspace->users()->attach($this->trainer->id, ['role' => 'trainer', 'is_active' => true]);

        $this->student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_ACTIVE,
        ]);
    }

    // ── Attendance Rate ────────────────────────────────────────

    public function test_attendance_rate_with_done_and_no_show(): void
    {
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->startOfDay()->addHours(8),
            'ends_at' => now()->startOfDay()->addHours(9),
            'status' => Appointment::STATUS_DONE,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->startOfDay()->addHours(10),
            'ends_at' => now()->startOfDay()->addHours(11),
            'status' => Appointment::STATUS_NO_SHOW,
        ]);

        $summary = $this->service->summary($this->workspace->id);

        $this->assertEquals(50.0, $summary['appointments']['today_attendance_rate']);
    }

    public function test_attendance_rate_null_when_no_done_or_no_show(): void
    {
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->startOfDay()->addHours(8),
            'ends_at' => now()->startOfDay()->addHours(9),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $summary = $this->service->summary($this->workspace->id);

        $this->assertNull($summary['appointments']['today_attendance_rate']);
    }

    public function test_attendance_rate_100_when_all_done(): void
    {
        Appointment::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->startOfDay()->addHours(8),
            'ends_at' => now()->startOfDay()->addHours(9),
            'status' => Appointment::STATUS_DONE,
        ]);

        $summary = $this->service->summary($this->workspace->id);

        $this->assertEquals(100.0, $summary['appointments']['today_attendance_rate']);
    }

    // ── Student Counts ─────────────────────────────────────────

    public function test_student_counts_active_and_passive(): void
    {
        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_PASSIVE,
        ]);

        $summary = $this->service->summary($this->workspace->id);

        $this->assertEquals(2, $summary['students']['total']);
        $this->assertEquals(1, $summary['students']['active']);
        $this->assertEquals(1, $summary['students']['passive']);
    }

    // ── Upcoming 7-day Appointment Count ───────────────────────

    public function test_upcoming_7d_counts_planned_and_done(): void
    {
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDays(3)->startOfDay()->addHours(10),
            'ends_at' => now()->addDays(3)->startOfDay()->addHours(11),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDays(5)->startOfDay()->addHours(10),
            'ends_at' => now()->addDays(5)->startOfDay()->addHours(11),
            'status' => Appointment::STATUS_DONE,
        ]);

        // Cancelled should not count
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDays(2)->startOfDay()->addHours(10),
            'ends_at' => now()->addDays(2)->startOfDay()->addHours(11),
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        // Beyond 7 days should not count
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDays(10)->startOfDay()->addHours(10),
            'ends_at' => now()->addDays(10)->startOfDay()->addHours(11),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $summary = $this->service->summary($this->workspace->id);

        $this->assertEquals(2, $summary['appointments']['upcoming_7d']);
    }

    // ── Trainer Role Scope ─────────────────────────────────────

    public function test_trainer_scope_filters_by_trainer_user_id(): void
    {
        $trainerB = User::factory()->trainer()->create();
        $this->workspace->users()->attach($trainerB->id, ['role' => 'trainer', 'is_active' => true]);

        $studentB = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerB->id,
            'status' => Student::STATUS_ACTIVE,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->startOfDay()->addHours(8),
            'ends_at' => now()->startOfDay()->addHours(9),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerB->id,
            'student_id' => $studentB->id,
            'starts_at' => now()->startOfDay()->addHours(10),
            'ends_at' => now()->startOfDay()->addHours(11),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $summary = $this->service->summary($this->workspace->id, $this->trainer->id);

        $this->assertEquals(1, $summary['students']['total']);
        $this->assertEquals(1, $summary['appointments']['today_total']);
    }

    public function test_owner_admin_null_trainer_sees_all(): void
    {
        $trainerB = User::factory()->trainer()->create();
        $this->workspace->users()->attach($trainerB->id, ['role' => 'trainer', 'is_active' => true]);

        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerB->id,
            'status' => Student::STATUS_ACTIVE,
        ]);

        $summary = $this->service->summary($this->workspace->id, null);

        $this->assertEquals(2, $summary['students']['total']);
    }

    // ── Programs ───────────────────────────────────────────────

    public function test_programs_active_and_draft_this_week(): void
    {
        Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'student_id' => $this->student->id,
            'trainer_user_id' => $this->trainer->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'status' => Program::STATUS_ACTIVE,
        ]);

        Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'student_id' => $this->student->id,
            'trainer_user_id' => $this->trainer->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'status' => Program::STATUS_DRAFT,
        ]);

        $summary = $this->service->summary($this->workspace->id);

        $this->assertEquals(1, $summary['programs']['active_this_week']);
        $this->assertEquals(1, $summary['programs']['draft_this_week']);
    }

    // ── Reminders ──────────────────────────────────────────────

    public function test_reminders_today_counts(): void
    {
        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => now()->startOfDay()->addHours(8),
            'status' => AppointmentReminder::STATUS_SENT,
        ]);

        AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => now()->startOfDay()->addHours(10),
            'status' => AppointmentReminder::STATUS_MISSED,
        ]);

        $summary = $this->service->summary($this->workspace->id);

        $this->assertEquals(2, $summary['reminders']['today_total']);
        $this->assertEquals(1, $summary['reminders']['today_sent']);
        $this->assertEquals(1, $summary['reminders']['today_missed']);
    }

    // ── Empty State ────────────────────────────────────────────

    public function test_empty_workspace_returns_zero_defaults(): void
    {
        $emptyWorkspace = Workspace::factory()->create(['owner_user_id' => User::factory()->ownerAdmin()->create()->id]);

        $summary = $this->service->summary($emptyWorkspace->id);

        $this->assertEquals(0, $summary['students']['total']);
        $this->assertEquals(0, $summary['appointments']['today_total']);
        $this->assertNull($summary['appointments']['today_attendance_rate']);
        $this->assertEquals(0, $summary['programs']['active_this_week']);
        $this->assertEquals(0, $summary['reminders']['today_total']);
    }
}
