<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AppointmentReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppointmentReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    private AppointmentReminderService $service;

    private Workspace $workspace;

    private User $trainer;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AppointmentReminderService;

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

    private function createAppointment(array $overrides = []): Appointment
    {
        return Appointment::factory()->create(array_merge([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => Appointment::STATUS_PLANNED,
        ], $overrides));
    }

    // ── syncForAppointment ─────────────────────────────────────

    public function test_sync_creates_reminders_based_on_default_offsets(): void
    {
        $appointment = $this->createAppointment([
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
        ]);

        $this->service->syncForAppointment($appointment);

        $reminders = $appointment->reminders()->get();
        $this->assertCount(2, $reminders);

        $offsets = $reminders->pluck('payload')->map(fn ($p) => $p['offset_minutes'])->sort()->values();
        $this->assertEquals([120, 1440], $offsets->all());
    }

    public function test_sync_creates_reminders_with_workspace_policy_offsets(): void
    {
        $this->workspace->update(['reminder_policy' => ['whatsapp_offsets_minutes' => [60, 30]]]);

        $appointment = $this->createAppointment([
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
        ]);
        $appointment->load('workspace');

        $this->service->syncForAppointment($appointment, $this->workspace->reminder_policy);

        $reminders = $appointment->reminders()->get();
        $this->assertCount(2, $reminders);

        $offsets = $reminders->pluck('payload')->map(fn ($p) => $p['offset_minutes'])->sort()->values();
        $this->assertEquals([30, 60], $offsets->all());
    }

    public function test_sync_marks_past_reminders_as_missed(): void
    {
        // Appointment 3 hours from now: 1440-min offset is past (missed), 120-min offset is still in future (pending)
        $appointment = $this->createAppointment([
            'starts_at' => now()->addMinutes(180),
            'ends_at' => now()->addMinutes(240),
        ]);

        $this->service->syncForAppointment($appointment);

        $reminders = $appointment->reminders()->get();
        $missedReminder = $reminders->firstWhere('payload.offset_minutes', 1440);
        $pendingReminder = $reminders->firstWhere('payload.offset_minutes', 120);

        $this->assertEquals(AppointmentReminder::STATUS_MISSED, $missedReminder->status);
        $this->assertEquals(AppointmentReminder::STATUS_PENDING, $pendingReminder->status);
    }

    public function test_sync_removes_orphaned_reminders(): void
    {
        $appointment = $this->createAppointment([
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
        ]);

        $this->service->syncForAppointment($appointment);
        $this->assertCount(2, $appointment->reminders()->get());

        $this->service->syncForAppointment($appointment, ['whatsapp_offsets_minutes' => [60]]);
        $this->assertCount(1, $appointment->reminders()->get());
    }

    public function test_sync_cancels_pending_for_cancelled_appointment(): void
    {
        $appointment = $this->createAppointment([
            'status' => Appointment::STATUS_CANCELLED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $appointment->reminders()->create([
            'workspace_id' => $this->workspace->id,
            'channel' => 'whatsapp',
            'scheduled_for' => now()->addHours(20),
            'status' => AppointmentReminder::STATUS_PENDING,
        ]);

        $this->service->syncForAppointment($appointment);

        $reminder = $appointment->reminders()->first();
        $this->assertEquals(AppointmentReminder::STATUS_CANCELLED, $reminder->status);
    }

    // ── canTransition ──────────────────────────────────────────

    public function test_same_status_transition_is_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('pending', 'pending'));
    }

    public function test_pending_to_ready_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('pending', 'ready'));
    }

    public function test_pending_to_cancelled_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('pending', 'cancelled'));
    }

    public function test_pending_to_missed_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('pending', 'missed'));
    }

    public function test_pending_to_failed_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('pending', 'failed'));
    }

    public function test_pending_to_sent_not_allowed(): void
    {
        $this->assertFalse($this->service->canTransition('pending', 'sent'));
    }

    public function test_ready_to_sent_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('ready', 'sent'));
    }

    public function test_ready_to_cancelled_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('ready', 'cancelled'));
    }

    public function test_missed_to_pending_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('missed', 'pending'));
    }

    public function test_missed_to_escalated_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('missed', 'escalated'));
    }

    public function test_missed_to_sent_not_allowed(): void
    {
        $this->assertFalse($this->service->canTransition('missed', 'sent'));
    }

    public function test_failed_to_pending_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('failed', 'pending'));
    }

    public function test_failed_to_escalated_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('failed', 'escalated'));
    }

    public function test_escalated_to_pending_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('escalated', 'pending'));
    }

    public function test_escalated_to_cancelled_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('escalated', 'cancelled'));
    }

    public function test_escalated_to_sent_not_allowed(): void
    {
        $this->assertFalse($this->service->canTransition('escalated', 'sent'));
    }

    public function test_sent_is_terminal(): void
    {
        $this->assertFalse($this->service->canTransition('sent', 'pending'));
        $this->assertFalse($this->service->canTransition('sent', 'ready'));
        $this->assertFalse($this->service->canTransition('sent', 'cancelled'));
    }

    public function test_cancelled_is_terminal(): void
    {
        $this->assertFalse($this->service->canTransition('cancelled', 'pending'));
        $this->assertFalse($this->service->canTransition('cancelled', 'ready'));
    }

    // ── isInQuietPeriod ────────────────────────────────────────

    public function test_quiet_period_disabled_returns_false(): void
    {
        $timestamp = Carbon::parse('2026-06-10 23:00:00', 'UTC');

        $this->assertFalse($this->service->isInQuietPeriod($timestamp, []));
    }

    public function test_quiet_period_enabled_inside_range(): void
    {
        $timestamp = Carbon::parse('2026-06-10 23:00:00', 'UTC');

        $policy = [
            'quiet_hours' => [
                'enabled' => true,
                'timezone' => 'UTC',
                'start' => '22:00',
                'end' => '08:00',
            ],
        ];

        $this->assertTrue($this->service->isInQuietPeriod($timestamp, $policy));
    }

    public function test_quiet_period_enabled_outside_range(): void
    {
        $timestamp = Carbon::parse('2026-06-10 14:00:00', 'UTC');

        $policy = [
            'quiet_hours' => [
                'enabled' => true,
                'timezone' => 'UTC',
                'start' => '22:00',
                'end' => '08:00',
            ],
        ];

        $this->assertFalse($this->service->isInQuietPeriod($timestamp, $policy));
    }

    public function test_quiet_period_early_morning_in_range(): void
    {
        $timestamp = Carbon::parse('2026-06-11 03:00:00', 'UTC');

        $policy = [
            'quiet_hours' => [
                'enabled' => true,
                'timezone' => 'UTC',
                'start' => '22:00',
                'end' => '08:00',
            ],
        ];

        $this->assertTrue($this->service->isInQuietPeriod($timestamp, $policy));
    }

    public function test_weekend_mute_saturday(): void
    {
        // 2026-06-13 is Saturday
        $timestamp = Carbon::parse('2026-06-13 14:00:00', 'UTC');

        $policy = ['weekend_mute' => true, 'quiet_hours' => ['timezone' => 'UTC']];

        $this->assertTrue($this->service->isInQuietPeriod($timestamp, $policy));
    }

    public function test_weekend_mute_sunday(): void
    {
        // 2026-06-14 is Sunday
        $timestamp = Carbon::parse('2026-06-14 10:00:00', 'UTC');

        $policy = ['weekend_mute' => true, 'quiet_hours' => ['timezone' => 'UTC']];

        $this->assertTrue($this->service->isInQuietPeriod($timestamp, $policy));
    }

    public function test_weekend_mute_weekday_returns_false(): void
    {
        // 2026-06-10 is Wednesday
        $timestamp = Carbon::parse('2026-06-10 10:00:00', 'UTC');

        $policy = ['weekend_mute' => true, 'quiet_hours' => ['timezone' => 'UTC']];

        $this->assertFalse($this->service->isInQuietPeriod($timestamp, $policy));
    }

    // ── retryFailed ────────────────────────────────────────────

    public function test_retry_failed_requeues_within_max_attempts(): void
    {
        $appointment = $this->createAppointment();

        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'status' => AppointmentReminder::STATUS_FAILED,
            'attempt_count' => 0,
            'next_retry_at' => now()->subMinute(),
        ]);

        $affected = $this->service->retryFailed();

        $this->assertEquals(1, $affected);
        $reminder->refresh();
        $this->assertEquals(AppointmentReminder::STATUS_PENDING, $reminder->status);
        $this->assertEquals(1, $reminder->attempt_count);
    }

    public function test_retry_failed_skips_when_max_attempts_reached(): void
    {
        $appointment = $this->createAppointment();

        AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'status' => AppointmentReminder::STATUS_FAILED,
            'attempt_count' => 2,
            'next_retry_at' => now()->subMinute(),
        ]);

        $affected = $this->service->retryFailed();

        $this->assertEquals(0, $affected);
    }

    // ── escalateStale ──────────────────────────────────────────

    public function test_escalate_stale_escalates_exhausted_reminders(): void
    {
        $appointment = $this->createAppointment();

        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'status' => AppointmentReminder::STATUS_FAILED,
            'attempt_count' => 2,
        ]);

        $affected = $this->service->escalateStale();

        $this->assertEquals(1, $affected);
        $this->assertEquals(AppointmentReminder::STATUS_ESCALATED, $reminder->fresh()->status);
        $this->assertNotNull($reminder->fresh()->escalated_at);
    }

    public function test_escalate_stale_skips_when_attempts_remain(): void
    {
        $appointment = $this->createAppointment();

        AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'status' => AppointmentReminder::STATUS_FAILED,
            'attempt_count' => 1,
        ]);

        $affected = $this->service->escalateStale();

        $this->assertEquals(0, $affected);
    }

    // ── markMissed ─────────────────────────────────────────────

    public function test_mark_missed_marks_past_appointment_reminders(): void
    {
        $appointment = $this->createAppointment([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->subMinutes(1),
        ]);

        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'status' => AppointmentReminder::STATUS_PENDING,
        ]);

        $affected = $this->service->markMissed();

        $this->assertEquals(1, $affected);
        $this->assertEquals(AppointmentReminder::STATUS_MISSED, $reminder->fresh()->status);
    }

    public function test_mark_missed_ignores_future_appointments(): void
    {
        $appointment = $this->createAppointment([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'status' => AppointmentReminder::STATUS_PENDING,
        ]);

        $affected = $this->service->markMissed();

        $this->assertEquals(0, $affected);
    }

    // ── prepareReady ───────────────────────────────────────────

    public function test_prepare_ready_promotes_due_pending_reminders(): void
    {
        $appointment = $this->createAppointment();

        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'status' => AppointmentReminder::STATUS_PENDING,
            'scheduled_for' => now()->subMinute(),
        ]);

        $affected = $this->service->prepareReady();

        $this->assertEquals(1, $affected);
        $this->assertEquals(AppointmentReminder::STATUS_READY, $reminder->fresh()->status);
    }

    public function test_prepare_ready_skips_during_quiet_hours(): void
    {
        $this->workspace->update([
            'reminder_policy' => [
                'quiet_hours' => [
                    'enabled' => true,
                    'timezone' => 'UTC',
                    'start' => '00:00',
                    'end' => '23:59',
                ],
            ],
        ]);

        $appointment = $this->createAppointment();

        AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'status' => AppointmentReminder::STATUS_PENDING,
            'scheduled_for' => now()->subMinute(),
        ]);

        $affected = $this->service->prepareReady();

        $this->assertEquals(0, $affected);
    }

    // ── resolveOffsetsMinutes ──────────────────────────────────

    public function test_resolve_offsets_returns_defaults_when_no_policy(): void
    {
        $offsets = $this->service->resolveOffsetsMinutes();

        $this->assertEquals([1440, 120], $offsets);
    }

    public function test_resolve_offsets_uses_workspace_policy(): void
    {
        $offsets = $this->service->resolveOffsetsMinutes(['whatsapp_offsets_minutes' => [60, 30]]);

        $this->assertEquals([60, 30], $offsets);
    }

    public function test_resolve_offsets_filters_negative_and_zero_values(): void
    {
        $offsets = $this->service->resolveOffsetsMinutes(['whatsapp_offsets_minutes' => [0, -5, 60]]);

        $this->assertEquals([60], $offsets);
    }

    public function test_resolve_offsets_falls_back_to_defaults_on_empty(): void
    {
        $offsets = $this->service->resolveOffsetsMinutes(['whatsapp_offsets_minutes' => []]);

        $this->assertEquals([1440, 120], $offsets);
    }

    public function test_resolve_offsets_removes_duplicates(): void
    {
        $offsets = $this->service->resolveOffsetsMinutes(['whatsapp_offsets_minutes' => [60, 60, 30]]);

        $this->assertEquals([60, 30], $offsets);
    }

    // ── resolveRetryPolicy ─────────────────────────────────────

    public function test_resolve_retry_policy_defaults(): void
    {
        $policy = $this->service->resolveRetryPolicy();

        $this->assertEquals(2, $policy['max_attempts']);
        $this->assertEquals([15, 30], $policy['backoff_minutes']);
        $this->assertTrue($policy['escalate_on_exhausted']);
    }

    public function test_resolve_retry_policy_from_workspace(): void
    {
        $policy = $this->service->resolveRetryPolicy([
            'retry' => [
                'max_attempts' => 3,
                'backoff_minutes' => [5, 10, 20],
                'escalate_on_exhausted' => false,
            ],
        ]);

        $this->assertEquals(3, $policy['max_attempts']);
        $this->assertEquals([5, 10, 20], $policy['backoff_minutes']);
        $this->assertFalse($policy['escalate_on_exhausted']);
    }

    // ── requeue ────────────────────────────────────────────────

    public function test_requeue_transitions_escalated_to_pending(): void
    {
        $appointment = $this->createAppointment();

        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'status' => AppointmentReminder::STATUS_ESCALATED,
            'escalated_at' => now(),
        ]);

        $result = $this->service->requeue($reminder, 'manual_requeue');

        $this->assertEquals(AppointmentReminder::STATUS_PENDING, $result->status);
        $this->assertNull($result->escalated_at);
        $this->assertEquals('manual_requeue', $result->failure_reason);
    }

    public function test_requeue_does_not_change_sent_reminder(): void
    {
        $appointment = $this->createAppointment();

        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $appointment->id,
            'status' => AppointmentReminder::STATUS_SENT,
            'marked_sent_at' => now(),
        ]);

        $result = $this->service->requeue($reminder);

        $this->assertEquals(AppointmentReminder::STATUS_SENT, $result->status);
    }
}
