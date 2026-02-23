<?php

namespace Tests\Unit\Services;

use App\Models\Program;
use App\Models\ProgramTemplate;
use App\Models\ProgramTemplateItem;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProgramServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProgramService $service;

    private Workspace $workspace;

    private User $trainer;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProgramService;

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

    // ── listTemplates ──────────────────────────────────────────

    public function test_list_templates_returns_paginated_results(): void
    {
        ProgramTemplate::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $result = $this->service->listTemplates($this->workspace->id, null, ['per_page' => 2]);

        $this->assertCount(2, $result->items());
        $this->assertEquals(3, $result->total());
    }

    public function test_list_templates_scopes_by_trainer(): void
    {
        ProgramTemplate::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $otherTrainer = User::factory()->trainer()->create();
        ProgramTemplate::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $otherTrainer->id,
        ]);

        $result = $this->service->listTemplates($this->workspace->id, $this->trainer->id, []);

        $this->assertCount(1, $result->items());
    }

    // ── listPrograms ─────────────────────────────────────────

    public function test_list_programs_returns_paginated_results(): void
    {
        Program::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
            'student_id' => $this->student->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $result = $this->service->listPrograms($this->student->id, ['per_page' => 2]);

        $this->assertCount(2, $result->items());
        $this->assertEquals(3, $result->total());
    }

    public function test_list_programs_filters_by_status(): void
    {
        Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'student_id' => $this->student->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Program::STATUS_ACTIVE,
            'week_start_date' => '2026-06-01',
        ]);
        Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'student_id' => $this->student->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Program::STATUS_DRAFT,
            'week_start_date' => '2026-06-08',
        ]);

        $result = $this->service->listPrograms($this->student->id, ['status' => Program::STATUS_ACTIVE]);

        $this->assertCount(1, $result->items());
    }

    // ── createFromTemplate ─────────────────────────────────────

    public function test_create_from_template_copies_items(): void
    {
        $template = ProgramTemplate::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        ProgramTemplateItem::factory()->create([
            'program_template_id' => $template->id,
            'day_of_week' => 1,
            'order_no' => 1,
            'exercise' => 'Squat',
            'sets' => 3,
            'reps' => 10,
        ]);

        ProgramTemplateItem::factory()->create([
            'program_template_id' => $template->id,
            'day_of_week' => 1,
            'order_no' => 2,
            'exercise' => 'Bench Press',
            'sets' => 4,
            'reps' => 8,
        ]);

        $program = $this->service->createFromTemplate($this->student, $this->trainer->id, $template, [
            'week_start_date' => '2026-06-08',
        ]);

        $this->assertEquals($template->title, $program->title);
        $this->assertEquals($template->goal, $program->goal);
        $this->assertCount(2, $program->items);
        $this->assertEquals('Squat', $program->items->first()->exercise);
    }

    public function test_create_from_template_allows_title_override(): void
    {
        $template = ProgramTemplate::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'title' => 'Template Title',
        ]);

        $program = $this->service->createFromTemplate($this->student, $this->trainer->id, $template, [
            'week_start_date' => '2026-06-08',
            'title' => 'Custom Title',
        ]);

        $this->assertEquals('Custom Title', $program->title);
    }

    public function test_create_from_template_defaults_to_draft(): void
    {
        $template = ProgramTemplate::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $program = $this->service->createFromTemplate($this->student, $this->trainer->id, $template, [
            'week_start_date' => '2026-06-08',
        ]);

        $this->assertEquals(Program::STATUS_DRAFT, $program->status);
    }

    // ── copyWeek ───────────────────────────────────────────────

    public function test_copy_week_creates_program_from_source(): void
    {
        $source = Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'student_id' => $this->student->id,
            'trainer_user_id' => $this->trainer->id,
            'title' => 'Source Program',
            'week_start_date' => '2026-06-01',
            'status' => Program::STATUS_ACTIVE,
        ]);

        $source->items()->create([
            'day_of_week' => 1,
            'order_no' => 1,
            'exercise' => 'Deadlift',
            'sets' => 5,
            'reps' => 5,
        ]);

        $program = $this->service->copyWeek($this->student, $this->trainer->id, [
            'source_week_start_date' => '2026-06-01',
            'target_week_start_date' => '2026-06-08',
        ]);

        $this->assertEquals('Source Program', $program->title);
        $this->assertEquals('2026-06-08', $program->week_start_date->toDateString());
        $this->assertCount(1, $program->items);
        $this->assertEquals('Deadlift', $program->items->first()->exercise);
    }

    public function test_copy_week_throws_when_source_not_found(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->copyWeek($this->student, $this->trainer->id, [
            'source_week_start_date' => '2026-01-01',
            'target_week_start_date' => '2026-06-08',
        ]);
    }

    public function test_copy_week_defaults_to_draft(): void
    {
        Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'student_id' => $this->student->id,
            'trainer_user_id' => $this->trainer->id,
            'week_start_date' => '2026-06-01',
            'status' => Program::STATUS_ACTIVE,
        ]);

        $program = $this->service->copyWeek($this->student, $this->trainer->id, [
            'source_week_start_date' => '2026-06-01',
            'target_week_start_date' => '2026-06-08',
        ]);

        $this->assertEquals(Program::STATUS_DRAFT, $program->status);
    }

    // ── Single Active Per Week ─────────────────────────────────

    public function test_cannot_have_two_active_programs_same_week(): void
    {
        Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'student_id' => $this->student->id,
            'trainer_user_id' => $this->trainer->id,
            'week_start_date' => '2026-06-08',
            'status' => Program::STATUS_ACTIVE,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->create($this->student, $this->trainer->id, [
            'title' => 'Second Active',
            'week_start_date' => '2026-06-08',
            'status' => Program::STATUS_ACTIVE,
        ]);
    }

    public function test_draft_programs_allowed_same_week_as_active(): void
    {
        Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'student_id' => $this->student->id,
            'trainer_user_id' => $this->trainer->id,
            'week_start_date' => '2026-06-08',
            'status' => Program::STATUS_ACTIVE,
        ]);

        $program = $this->service->create($this->student, $this->trainer->id, [
            'title' => 'Draft Program',
            'week_start_date' => '2026-06-08',
            'status' => Program::STATUS_DRAFT,
        ]);

        $this->assertEquals(Program::STATUS_DRAFT, $program->status);
    }

    public function test_update_status_to_active_blocks_if_another_active_exists(): void
    {
        Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'student_id' => $this->student->id,
            'trainer_user_id' => $this->trainer->id,
            'week_start_date' => '2026-06-08',
            'status' => Program::STATUS_ACTIVE,
        ]);

        $draft = Program::factory()->create([
            'workspace_id' => $this->workspace->id,
            'student_id' => $this->student->id,
            'trainer_user_id' => $this->trainer->id,
            'week_start_date' => '2026-06-08',
            'status' => Program::STATUS_DRAFT,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->updateStatus($draft, Program::STATUS_ACTIVE);
    }

    // ── Create ─────────────────────────────────────────────────

    public function test_create_program_with_items(): void
    {
        $program = $this->service->create($this->student, $this->trainer->id, [
            'title' => 'Full Body',
            'goal' => 'Strength',
            'week_start_date' => '2026-06-08',
            'items' => [
                ['day_of_week' => 1, 'order_no' => 1, 'exercise' => 'Squat', 'sets' => 3, 'reps' => 10],
                ['day_of_week' => 1, 'order_no' => 2, 'exercise' => 'Bench', 'sets' => 3, 'reps' => 10],
            ],
        ]);

        $this->assertEquals('Full Body', $program->title);
        $this->assertEquals('Strength', $program->goal);
        $this->assertCount(2, $program->items);
        $this->assertTrue($program->relationLoaded('student'));
        $this->assertTrue($program->relationLoaded('trainer'));
    }

    // ── Template Name Uniqueness ───────────────────────────────

    public function test_create_template_enforces_case_insensitive_unique_name(): void
    {
        $this->service->createTemplate($this->workspace->id, $this->trainer->id, [
            'name' => 'Beginner Plan',
            'title' => 'Beginner Program',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->createTemplate($this->workspace->id, $this->trainer->id, [
            'name' => 'beginner plan',
            'title' => 'Different Title',
        ]);
    }

    public function test_different_trainers_can_have_same_template_name(): void
    {
        $trainerB = User::factory()->trainer()->create();
        $this->workspace->users()->attach($trainerB->id, ['role' => 'trainer', 'is_active' => true]);

        $this->service->createTemplate($this->workspace->id, $this->trainer->id, [
            'name' => 'Beginner Plan',
            'title' => 'Beginner Program',
        ]);

        $template = $this->service->createTemplate($this->workspace->id, $trainerB->id, [
            'name' => 'Beginner Plan',
            'title' => 'Different Template',
        ]);

        $this->assertNotNull($template->id);
    }

    public function test_update_template_enforces_unique_name_excluding_self(): void
    {
        $template = $this->service->createTemplate($this->workspace->id, $this->trainer->id, [
            'name' => 'Plan A',
            'title' => 'Plan A Title',
        ]);

        $this->service->createTemplate($this->workspace->id, $this->trainer->id, [
            'name' => 'Plan B',
            'title' => 'Plan B Title',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->updateTemplate($template, ['name' => 'Plan B']);
    }

    public function test_update_template_allows_same_name_for_self(): void
    {
        $template = $this->service->createTemplate($this->workspace->id, $this->trainer->id, [
            'name' => 'Plan A',
            'title' => 'Plan A Title',
        ]);

        $updated = $this->service->updateTemplate($template, ['name' => 'Plan A', 'title' => 'New Title']);

        $this->assertEquals('New Title', $updated->title);
    }
}
