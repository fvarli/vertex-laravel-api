<?php

namespace Tests\Unit\Services;

use App\Models\AppointmentSeries;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AppointmentSeriesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentSeriesServiceTest extends TestCase
{
    use RefreshDatabase;

    private AppointmentSeriesService $service;

    private Workspace $workspace;

    private User $trainer;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AppointmentSeriesService::class);

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

    public function test_list_returns_paginated_series(): void
    {
        AppointmentSeries::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
        ]);

        $result = $this->service->list($this->workspace->id, null, ['per_page' => 2]);

        $this->assertCount(2, $result->items());
        $this->assertEquals(3, $result->total());
    }

    public function test_list_scopes_by_trainer(): void
    {
        AppointmentSeries::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
        ]);

        $otherTrainer = User::factory()->trainer()->create();
        $otherStudent = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $otherTrainer->id,
        ]);
        AppointmentSeries::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $otherTrainer->id,
            'student_id' => $otherStudent->id,
        ]);

        $result = $this->service->list($this->workspace->id, $this->trainer->id, []);

        $this->assertCount(1, $result->items());
    }

    public function test_list_filters_by_status(): void
    {
        AppointmentSeries::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'status' => AppointmentSeries::STATUS_ACTIVE,
        ]);
        AppointmentSeries::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'status' => AppointmentSeries::STATUS_PAUSED,
        ]);

        $result = $this->service->list($this->workspace->id, null, ['status' => AppointmentSeries::STATUS_ACTIVE]);

        $this->assertCount(1, $result->items());
    }
}
