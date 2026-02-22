<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;

    private User $owner;

    private User $trainer;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ReportService;

        $this->owner = User::factory()->ownerAdmin()->create();
        $this->trainer = User::factory()->trainer()->create();
        $this->workspace = Workspace::factory()->create(['owner_user_id' => $this->owner->id]);
    }

    // ---------------------------------------------------------------
    // appointments() — status counting
    // ---------------------------------------------------------------

    public function test_appointments_returns_correct_status_counts(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $base = [
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
        ];

        Appointment::factory()->create($base + [
            'starts_at' => '2026-03-10 09:00:00',
            'ends_at' => '2026-03-10 10:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Appointment::factory()->create($base + [
            'starts_at' => '2026-03-10 11:00:00',
            'ends_at' => '2026-03-10 12:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);

        Appointment::factory()->create($base + [
            'starts_at' => '2026-03-11 09:00:00',
            'ends_at' => '2026-03-11 10:00:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        Appointment::factory()->create($base + [
            'starts_at' => '2026-03-12 09:00:00',
            'ends_at' => '2026-03-12 10:00:00',
            'status' => Appointment::STATUS_NO_SHOW,
        ]);

        $result = $this->service->appointments(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(4, $result['totals']['total']);
        $this->assertEquals(1, $result['totals']['planned']);
        $this->assertEquals(1, $result['totals']['done']);
        $this->assertEquals(1, $result['totals']['cancelled']);
        $this->assertEquals(1, $result['totals']['no_show']);
    }

    // ---------------------------------------------------------------
    // appointments() — day grouping
    // ---------------------------------------------------------------

    public function test_appointments_groups_by_day_correctly(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $base = [
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
        ];

        // Two appointments on 2026-03-10
        Appointment::factory()->create($base + [
            'starts_at' => '2026-03-10 09:00:00',
            'ends_at' => '2026-03-10 10:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);
        Appointment::factory()->create($base + [
            'starts_at' => '2026-03-10 11:00:00',
            'ends_at' => '2026-03-10 12:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);

        // One appointment on 2026-03-11
        Appointment::factory()->create($base + [
            'starts_at' => '2026-03-11 09:00:00',
            'ends_at' => '2026-03-11 10:00:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        $result = $this->service->appointments(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertCount(2, $result['buckets']);

        $bucketsByKey = collect($result['buckets'])->keyBy('bucket');

        $day10 = $bucketsByKey->get('2026-03-10');
        $this->assertNotNull($day10);
        $this->assertEquals(2, $day10['total']);
        $this->assertEquals(1, $day10['planned']);
        $this->assertEquals(1, $day10['done']);

        $day11 = $bucketsByKey->get('2026-03-11');
        $this->assertNotNull($day11);
        $this->assertEquals(1, $day11['total']);
        $this->assertEquals(1, $day11['cancelled']);
    }

    // ---------------------------------------------------------------
    // appointments() — week grouping
    // ---------------------------------------------------------------

    public function test_appointments_groups_by_week_correctly(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $base = [
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
        ];

        // 2026-03-02 is a Monday (ISO week 10)
        Appointment::factory()->create($base + [
            'starts_at' => '2026-03-02 09:00:00',
            'ends_at' => '2026-03-02 10:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        // 2026-03-09 is a Monday (ISO week 11)
        Appointment::factory()->create($base + [
            'starts_at' => '2026-03-09 09:00:00',
            'ends_at' => '2026-03-09 10:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);
        Appointment::factory()->create($base + [
            'starts_at' => '2026-03-13 09:00:00',
            'ends_at' => '2026-03-13 10:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);

        $result = $this->service->appointments(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'week',
        );

        $bucketsByKey = collect($result['buckets'])->keyBy('bucket');

        $this->assertCount(2, $result['buckets']);
        $this->assertEquals(1, $bucketsByKey->get('2026-W10')['total']);
        $this->assertEquals(2, $bucketsByKey->get('2026-W11')['total']);
    }

    // ---------------------------------------------------------------
    // appointments() — month grouping
    // ---------------------------------------------------------------

    public function test_appointments_groups_by_month_correctly(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $base = [
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
        ];

        Appointment::factory()->create($base + [
            'starts_at' => '2026-03-15 09:00:00',
            'ends_at' => '2026-03-15 10:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);
        Appointment::factory()->create($base + [
            'starts_at' => '2026-04-10 09:00:00',
            'ends_at' => '2026-04-10 10:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);

        $result = $this->service->appointments(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-04-30'),
            'month',
        );

        $bucketsByKey = collect($result['buckets'])->keyBy('bucket');

        $this->assertCount(2, $result['buckets']);
        $this->assertEquals(1, $bucketsByKey->get('2026-03')['total']);
        $this->assertEquals(1, $bucketsByKey->get('2026-04')['total']);
    }

    // ---------------------------------------------------------------
    // appointments() — filters payload
    // ---------------------------------------------------------------

    public function test_appointments_includes_correct_filter_payload(): void
    {
        $from = CarbonImmutable::parse('2026-03-01');
        $to = CarbonImmutable::parse('2026-03-31');

        $result = $this->service->appointments(
            $this->workspace->id,
            null,
            $from,
            $to,
            'week',
        );

        $this->assertEquals($from->toDateTimeString(), $result['filters']['date_from']);
        $this->assertEquals($to->toDateTimeString(), $result['filters']['date_to']);
        $this->assertEquals('week', $result['filters']['group_by']);
    }

    // ---------------------------------------------------------------
    // students() — active/passive/new counts
    // ---------------------------------------------------------------

    public function test_students_returns_correct_totals(): void
    {
        // Active student created inside range
        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_ACTIVE,
            'created_at' => '2026-03-15 10:00:00',
        ]);

        // Passive student created inside range
        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_PASSIVE,
            'created_at' => '2026-03-20 10:00:00',
        ]);

        // Active student created outside range (still counted in total/active but not new_in_range)
        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_ACTIVE,
            'created_at' => '2026-01-10 10:00:00',
        ]);

        $result = $this->service->students(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(3, $result['totals']['total']);
        $this->assertEquals(2, $result['totals']['active']);
        $this->assertEquals(1, $result['totals']['passive']);
        $this->assertEquals(2, $result['totals']['new_in_range']);
    }

    // ---------------------------------------------------------------
    // students() — bucket grouping
    // ---------------------------------------------------------------

    public function test_students_groups_by_day_correctly(): void
    {
        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_ACTIVE,
            'created_at' => '2026-03-10 10:00:00',
        ]);

        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_PASSIVE,
            'created_at' => '2026-03-10 14:00:00',
        ]);

        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_ACTIVE,
            'created_at' => '2026-03-12 10:00:00',
        ]);

        $result = $this->service->students(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $bucketsByKey = collect($result['buckets'])->keyBy('bucket');

        $this->assertCount(2, $result['buckets']);

        $day10 = $bucketsByKey->get('2026-03-10');
        $this->assertNotNull($day10);
        $this->assertEquals(2, $day10['total']);
        $this->assertEquals(1, $day10['active']);
        $this->assertEquals(1, $day10['passive']);

        $day12 = $bucketsByKey->get('2026-03-12');
        $this->assertNotNull($day12);
        $this->assertEquals(1, $day12['total']);
        $this->assertEquals(1, $day12['active']);
    }

    // ---------------------------------------------------------------
    // programs() — status distribution
    // ---------------------------------------------------------------

    public function test_programs_returns_correct_status_distribution(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $base = [
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
        ];

        Program::factory()->create($base + [
            'week_start_date' => '2026-03-02',
            'status' => Program::STATUS_DRAFT,
        ]);

        Program::factory()->create($base + [
            'week_start_date' => '2026-03-09',
            'status' => Program::STATUS_ACTIVE,
        ]);

        Program::factory()->create($base + [
            'week_start_date' => '2026-03-09',
            'status' => Program::STATUS_ACTIVE,
        ]);

        Program::factory()->create($base + [
            'week_start_date' => '2026-03-16',
            'status' => Program::STATUS_ARCHIVED,
        ]);

        $result = $this->service->programs(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(4, $result['totals']['total']);
        $this->assertEquals(2, $result['totals']['active']);
        $this->assertEquals(1, $result['totals']['draft']);
        $this->assertEquals(1, $result['totals']['archived']);
    }

    // ---------------------------------------------------------------
    // programs() — bucket grouping
    // ---------------------------------------------------------------

    public function test_programs_groups_by_week_correctly(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $base = [
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
        ];

        // 2026-03-02 = Monday of ISO week 10
        Program::factory()->create($base + [
            'week_start_date' => '2026-03-02',
            'status' => Program::STATUS_DRAFT,
        ]);

        // 2026-03-09 = Monday of ISO week 11
        Program::factory()->create($base + [
            'week_start_date' => '2026-03-09',
            'status' => Program::STATUS_ACTIVE,
        ]);
        Program::factory()->create($base + [
            'week_start_date' => '2026-03-09',
            'status' => Program::STATUS_ARCHIVED,
        ]);

        $result = $this->service->programs(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'week',
        );

        $bucketsByKey = collect($result['buckets'])->keyBy('bucket');

        $this->assertCount(2, $result['buckets']);
        $this->assertEquals(1, $bucketsByKey->get('2026-W10')['total']);

        $week11 = $bucketsByKey->get('2026-W11');
        $this->assertEquals(2, $week11['total']);
        $this->assertEquals(1, $week11['active']);
        $this->assertEquals(1, $week11['archived']);
    }

    // ---------------------------------------------------------------
    // reminders() — rate calculations
    // ---------------------------------------------------------------

    public function test_reminders_returns_correct_rate_calculations(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-03-10 10:00:00',
            'ends_at' => '2026-03-10 11:00:00',
        ]);

        $baseReminder = [
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
        ];

        // 2 SENT reminders: 1 on-time, 1 late (unique scheduled_for per appointment+channel)
        AppointmentReminder::factory()->create($baseReminder + [
            'scheduled_for' => '2026-03-10 08:00:00',
            'status' => AppointmentReminder::STATUS_SENT,
            'marked_sent_at' => '2026-03-10 07:50:00', // before scheduled_for -> on time
            'attempt_count' => 1,
        ]);
        AppointmentReminder::factory()->create($baseReminder + [
            'scheduled_for' => '2026-03-10 08:01:00',
            'status' => AppointmentReminder::STATUS_SENT,
            'marked_sent_at' => '2026-03-10 08:30:00', // after scheduled_for -> late
            'attempt_count' => 2,
        ]);

        // 1 MISSED reminder
        AppointmentReminder::factory()->create($baseReminder + [
            'scheduled_for' => '2026-03-10 08:02:00',
            'status' => AppointmentReminder::STATUS_MISSED,
            'attempt_count' => 1,
        ]);

        // 1 PENDING reminder
        AppointmentReminder::factory()->create($baseReminder + [
            'scheduled_for' => '2026-03-10 08:03:00',
            'status' => AppointmentReminder::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        // 1 FAILED reminder
        AppointmentReminder::factory()->create($baseReminder + [
            'scheduled_for' => '2026-03-10 08:04:00',
            'status' => AppointmentReminder::STATUS_FAILED,
            'attempt_count' => 3,
        ]);

        $result = $this->service->reminders(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $totals = $result['totals'];

        // Total = 5
        $this->assertEquals(5, $totals['total']);
        $this->assertEquals(2, $totals['sent']);
        $this->assertEquals(1, $totals['pending']);
        $this->assertEquals(0, $totals['ready']);
        $this->assertEquals(1, $totals['failed']);
        $this->assertEquals(1, $totals['missed']);
        $this->assertEquals(0, $totals['escalated']);

        // send_rate = sent / total = 2/5 = 40.0%
        $this->assertEquals(40.0, $totals['send_rate']);

        // on_time_send_rate = on_time_sent / sent = 1/2 = 50.0%
        $this->assertEquals(50.0, $totals['on_time_send_rate']);

        // missed_rate = missed / total = 1/5 = 20.0%
        $this->assertEquals(20.0, $totals['missed_rate']);

        // avg_attempt_count = (1 + 2 + 1 + 0 + 3) / 5 = 1.4
        $this->assertEquals(1.4, $totals['avg_attempt_count']);
    }

    // ---------------------------------------------------------------
    // reminders() — escalated count
    // ---------------------------------------------------------------

    public function test_reminders_counts_escalated_correctly(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-03-10 10:00:00',
            'ends_at' => '2026-03-10 11:00:00',
        ]);

        $baseReminder = [
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
        ];

        AppointmentReminder::factory()->create($baseReminder + [
            'scheduled_for' => '2026-03-10 08:00:00',
            'status' => AppointmentReminder::STATUS_ESCALATED,
            'attempt_count' => 3,
        ]);

        AppointmentReminder::factory()->create($baseReminder + [
            'scheduled_for' => '2026-03-10 08:01:00',
            'status' => AppointmentReminder::STATUS_ESCALATED,
            'attempt_count' => 2,
        ]);

        AppointmentReminder::factory()->create($baseReminder + [
            'scheduled_for' => '2026-03-10 08:02:00',
            'status' => AppointmentReminder::STATUS_SENT,
            'marked_sent_at' => '2026-03-10 07:50:00',
            'attempt_count' => 1,
        ]);

        $result = $this->service->reminders(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(3, $result['totals']['total']);
        $this->assertEquals(2, $result['totals']['escalated']);
        $this->assertEquals(2, $result['totals']['escalated_count']);
    }

    // ---------------------------------------------------------------
    // reminders() — zero-division safety when no sent reminders
    // ---------------------------------------------------------------

    public function test_reminders_on_time_send_rate_is_zero_when_none_sent(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-03-10 10:00:00',
            'ends_at' => '2026-03-10 11:00:00',
        ]);

        AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'scheduled_for' => '2026-03-10 08:00:00',
            'status' => AppointmentReminder::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        $result = $this->service->reminders(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(0.0, $result['totals']['on_time_send_rate']);
        $this->assertEquals(0.0, $result['totals']['send_rate']);
    }

    // ---------------------------------------------------------------
    // reminders() — bucket grouping
    // ---------------------------------------------------------------

    public function test_reminders_groups_by_day_correctly(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-03-10 10:00:00',
            'ends_at' => '2026-03-10 11:00:00',
        ]);

        $baseReminder = [
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
        ];

        AppointmentReminder::factory()->create($baseReminder + [
            'scheduled_for' => '2026-03-10 08:00:00',
            'status' => AppointmentReminder::STATUS_SENT,
            'marked_sent_at' => '2026-03-10 07:50:00',
            'attempt_count' => 1,
        ]);
        AppointmentReminder::factory()->create($baseReminder + [
            'scheduled_for' => '2026-03-11 08:00:00',
            'status' => AppointmentReminder::STATUS_MISSED,
            'attempt_count' => 1,
        ]);
        AppointmentReminder::factory()->create($baseReminder + [
            'scheduled_for' => '2026-03-11 09:00:00',
            'status' => AppointmentReminder::STATUS_FAILED,
            'attempt_count' => 2,
        ]);

        $result = $this->service->reminders(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $bucketsByKey = collect($result['buckets'])->keyBy('bucket');

        $this->assertCount(2, $result['buckets']);

        $day10 = $bucketsByKey->get('2026-03-10');
        $this->assertNotNull($day10);
        $this->assertEquals(1, $day10['total']);
        $this->assertEquals(1, $day10['sent']);

        $day11 = $bucketsByKey->get('2026-03-11');
        $this->assertNotNull($day11);
        $this->assertEquals(2, $day11['total']);
        $this->assertEquals(1, $day11['missed']);
        $this->assertEquals(1, $day11['failed']);
    }

    // ---------------------------------------------------------------
    // Empty date range edge case
    // ---------------------------------------------------------------

    public function test_appointments_returns_empty_for_no_matching_date_range(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-06-15 10:00:00',
            'ends_at' => '2026-06-15 11:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);

        $result = $this->service->appointments(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(0, $result['totals']['total']);
        $this->assertEquals(0, $result['totals']['planned']);
        $this->assertEquals(0, $result['totals']['done']);
        $this->assertEquals(0, $result['totals']['cancelled']);
        $this->assertEquals(0, $result['totals']['no_show']);
        $this->assertEmpty($result['buckets']);
    }

    public function test_students_returns_zero_new_in_range_when_no_students_in_period(): void
    {
        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_ACTIVE,
            'created_at' => '2026-01-10 10:00:00',
        ]);

        $result = $this->service->students(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        // Total/active still count (they exist in workspace)
        $this->assertEquals(1, $result['totals']['total']);
        $this->assertEquals(1, $result['totals']['active']);
        // But new_in_range = 0 and buckets empty
        $this->assertEquals(0, $result['totals']['new_in_range']);
        $this->assertEmpty($result['buckets']);
    }

    public function test_programs_returns_empty_for_no_matching_date_range(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
            'week_start_date' => '2026-06-01',
            'status' => Program::STATUS_ACTIVE,
        ]);

        $result = $this->service->programs(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(0, $result['totals']['total']);
        $this->assertEmpty($result['buckets']);
    }

    public function test_reminders_returns_zero_rates_for_empty_date_range(): void
    {
        $result = $this->service->reminders(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(0, $result['totals']['total']);
        $this->assertEquals(0.0, $result['totals']['send_rate']);
        $this->assertEquals(0.0, $result['totals']['on_time_send_rate']);
        $this->assertEquals(0.0, $result['totals']['missed_rate']);
        $this->assertEquals(0.00, $result['totals']['avg_attempt_count']);
        $this->assertEmpty($result['buckets']);
    }

    // ---------------------------------------------------------------
    // Trainer filter scope restriction
    // ---------------------------------------------------------------

    public function test_appointments_trainer_filter_restricts_to_single_trainer(): void
    {
        $trainerA = $this->trainer;
        $trainerB = User::factory()->trainer()->create();

        $studentA = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerA->id,
        ]);
        $studentB = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerB->id,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerA->id,
            'student_id' => $studentA->id,
            'starts_at' => '2026-03-10 09:00:00',
            'ends_at' => '2026-03-10 10:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerA->id,
            'student_id' => $studentA->id,
            'starts_at' => '2026-03-11 09:00:00',
            'ends_at' => '2026-03-11 10:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerB->id,
            'student_id' => $studentB->id,
            'starts_at' => '2026-03-10 11:00:00',
            'ends_at' => '2026-03-10 12:00:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        $result = $this->service->appointments(
            $this->workspace->id,
            $trainerA->id,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(2, $result['totals']['total']);
        $this->assertEquals(1, $result['totals']['done']);
        $this->assertEquals(1, $result['totals']['planned']);
        $this->assertEquals(0, $result['totals']['cancelled']);
    }

    public function test_students_trainer_filter_restricts_scope(): void
    {
        $trainerA = $this->trainer;
        $trainerB = User::factory()->trainer()->create();

        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerA->id,
            'status' => Student::STATUS_ACTIVE,
            'created_at' => '2026-03-10 10:00:00',
        ]);
        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerB->id,
            'status' => Student::STATUS_ACTIVE,
            'created_at' => '2026-03-10 10:00:00',
        ]);

        $result = $this->service->students(
            $this->workspace->id,
            $trainerA->id,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(1, $result['totals']['total']);
        $this->assertEquals(1, $result['totals']['active']);
        $this->assertEquals(1, $result['totals']['new_in_range']);
    }

    public function test_programs_trainer_filter_restricts_scope(): void
    {
        $trainerA = $this->trainer;
        $trainerB = User::factory()->trainer()->create();

        $studentA = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerA->id,
        ]);
        $studentB = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerB->id,
        ]);

        Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerA->id,
            'student_id' => $studentA->id,
            'week_start_date' => '2026-03-09',
            'status' => Program::STATUS_ACTIVE,
        ]);
        Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerB->id,
            'student_id' => $studentB->id,
            'week_start_date' => '2026-03-09',
            'status' => Program::STATUS_DRAFT,
        ]);

        $result = $this->service->programs(
            $this->workspace->id,
            $trainerA->id,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(1, $result['totals']['total']);
        $this->assertEquals(1, $result['totals']['active']);
        $this->assertEquals(0, $result['totals']['draft']);
    }

    public function test_reminders_trainer_filter_restricts_via_appointment_relationship(): void
    {
        $trainerA = $this->trainer;
        $trainerB = User::factory()->trainer()->create();

        $studentA = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerA->id,
        ]);
        $studentB = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerB->id,
        ]);

        $appointmentA = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerA->id,
            'student_id' => $studentA->id,
            'starts_at' => '2026-03-10 10:00:00',
            'ends_at' => '2026-03-10 11:00:00',
        ]);
        $appointmentB = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerB->id,
            'student_id' => $studentB->id,
            'starts_at' => '2026-03-10 12:00:00',
            'ends_at' => '2026-03-10 13:00:00',
        ]);

        AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointmentA->id,
            'scheduled_for' => '2026-03-10 08:00:00',
            'status' => AppointmentReminder::STATUS_SENT,
            'marked_sent_at' => '2026-03-10 07:50:00',
            'attempt_count' => 1,
        ]);
        AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointmentB->id,
            'scheduled_for' => '2026-03-10 08:00:00',
            'status' => AppointmentReminder::STATUS_MISSED,
            'attempt_count' => 1,
        ]);

        $result = $this->service->reminders(
            $this->workspace->id,
            $trainerA->id,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(1, $result['totals']['total']);
        $this->assertEquals(1, $result['totals']['sent']);
        $this->assertEquals(0, $result['totals']['missed']);
        $this->assertEquals(100.0, $result['totals']['send_rate']);
    }

    // ---------------------------------------------------------------
    // Workspace isolation
    // ---------------------------------------------------------------

    public function test_appointments_only_returns_data_for_given_workspace(): void
    {
        $otherWorkspace = Workspace::factory()->create();

        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);
        $otherStudent = Student::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-03-10 09:00:00',
            'ends_at' => '2026-03-10 10:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);
        Appointment::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $otherStudent->id,
            'starts_at' => '2026-03-10 11:00:00',
            'ends_at' => '2026-03-10 12:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $result = $this->service->appointments(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(1, $result['totals']['total']);
        $this->assertEquals(1, $result['totals']['done']);
        $this->assertEquals(0, $result['totals']['planned']);
    }

    // ---------------------------------------------------------------
    // Null trainer (all trainers)
    // ---------------------------------------------------------------

    public function test_appointments_null_trainer_returns_all_trainers(): void
    {
        $trainerA = $this->trainer;
        $trainerB = User::factory()->trainer()->create();

        $studentA = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerA->id,
        ]);
        $studentB = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerB->id,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerA->id,
            'student_id' => $studentA->id,
            'starts_at' => '2026-03-10 09:00:00',
            'ends_at' => '2026-03-10 10:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $trainerB->id,
            'student_id' => $studentB->id,
            'starts_at' => '2026-03-10 11:00:00',
            'ends_at' => '2026-03-10 12:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $result = $this->service->appointments(
            $this->workspace->id,
            null,
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
            'day',
        );

        $this->assertEquals(2, $result['totals']['total']);
    }
}
