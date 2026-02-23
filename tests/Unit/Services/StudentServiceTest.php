<?php

namespace Tests\Unit\Services;

use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentService $service;

    private Workspace $workspace;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StudentService;

        $owner = User::factory()->ownerAdmin()->create();
        $this->workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $this->trainer = User::factory()->trainer()->create();
        $this->workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $this->workspace->users()->attach($this->trainer->id, ['role' => 'trainer', 'is_active' => true]);
    }

    public function test_list_returns_paginated_students(): void
    {
        Student::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $result = $this->service->list($this->workspace->id, null, ['per_page' => 2]);

        $this->assertCount(2, $result->items());
        $this->assertEquals(3, $result->total());
    }

    public function test_list_scopes_by_trainer(): void
    {
        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $otherTrainer = User::factory()->trainer()->create();
        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $otherTrainer->id,
        ]);

        $result = $this->service->list($this->workspace->id, $this->trainer->id, []);

        $this->assertCount(1, $result->items());
    }

    public function test_list_filters_by_status(): void
    {
        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_ACTIVE,
        ]);
        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_PASSIVE,
        ]);

        $result = $this->service->list($this->workspace->id, null, ['status' => Student::STATUS_ACTIVE]);

        $this->assertCount(1, $result->items());
    }

    public function test_create_returns_student(): void
    {
        $student = $this->service->create($this->workspace->id, $this->trainer->id, [
            'full_name' => 'Test Student',
            'phone' => '+905551234567',
        ]);

        $this->assertEquals('Test Student', $student->full_name);
        $this->assertEquals($this->workspace->id, $student->workspace_id);
        $this->assertEquals($this->trainer->id, $student->trainer_user_id);
        $this->assertEquals(Student::STATUS_ACTIVE, $student->status);
    }

    public function test_update_changes_student_fields(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'full_name' => 'Original Name',
        ]);

        $updated = $this->service->update($student, ['full_name' => 'Updated Name']);

        $this->assertEquals('Updated Name', $updated->full_name);
    }

    public function test_update_status_changes_status(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_ACTIVE,
        ]);

        $updated = $this->service->updateStatus($student, Student::STATUS_PASSIVE);

        $this->assertEquals(Student::STATUS_PASSIVE, $updated->status);
    }
}
