<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    private CalendarService $service;

    private Workspace $workspace;

    private User $trainer;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CalendarService;

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

    public function test_availability_returns_appointments_in_range(): void
    {
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $result = $this->service->availability($this->workspace->id, null, [
            'from' => '2026-06-10 00:00:00',
            'to' => '2026-06-10 23:59:59',
        ]);

        $this->assertCount(1, $result['appointments']);
        $this->assertCount(1, $result['days']);
    }

    public function test_availability_excludes_cancelled(): void
    {
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        $result = $this->service->availability($this->workspace->id, null, [
            'from' => '2026-06-10 00:00:00',
            'to' => '2026-06-10 23:59:59',
        ]);

        $this->assertCount(0, $result['appointments']);
    }

    public function test_availability_scopes_by_trainer(): void
    {
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $otherTrainer = User::factory()->trainer()->create();
        $otherStudent = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $otherTrainer->id,
        ]);
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $otherTrainer->id,
            'student_id' => $otherStudent->id,
            'starts_at' => '2026-06-10 14:00:00',
            'ends_at' => '2026-06-10 15:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $result = $this->service->availability($this->workspace->id, $this->trainer->id, [
            'from' => '2026-06-10 00:00:00',
            'to' => '2026-06-10 23:59:59',
        ]);

        $this->assertCount(1, $result['appointments']);
    }
}
