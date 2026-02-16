<?php

namespace Tests\Feature\Api\V1\ProgramTemplate;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgramTemplateFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_can_create_template_and_generate_program_from_template(): void
    {
        [$owner, $workspace] = $this->createOwnerWorkspaceContext();

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
        ]);

        Sanctum::actingAs($owner);

        $templateResponse = $this->postJson('/api/v1/program-templates', [
            'name' => 'strength-v1',
            'title' => 'Strength Base',
            'goal' => 'Build baseline strength',
            'items' => [
                [
                    'day_of_week' => 1,
                    'order_no' => 1,
                    'exercise' => 'Squat',
                    'sets' => 4,
                    'reps' => 8,
                ],
            ],
        ]);

        $templateResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'strength-v1');

        $templateId = (int) $templateResponse->json('data.id');

        $programResponse = $this->postJson("/api/v1/students/{$student->id}/programs/from-template", [
            'template_id' => $templateId,
            'week_start_date' => '2026-03-02',
            'status' => Program::STATUS_ACTIVE,
        ]);

        $programResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.items.0.exercise', 'Squat');
    }

    public function test_copy_week_creates_new_program_from_source_week(): void
    {
        [$owner, $workspace] = $this->createOwnerWorkspaceContext();

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
        ]);

        $sourceProgram = Program::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $student->id,
            'trainer_user_id' => $owner->id,
            'title' => 'Week One',
            'week_start_date' => '2026-03-02',
            'status' => Program::STATUS_DRAFT,
        ]);

        $sourceProgram->items()->create([
            'day_of_week' => 2,
            'order_no' => 1,
            'exercise' => 'Bench Press',
            'sets' => 4,
            'reps' => 10,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/students/{$student->id}/programs/copy-week", [
            'source_week_start_date' => '2026-03-02',
            'target_week_start_date' => '2026-03-09',
            'status' => Program::STATUS_ACTIVE,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.week_start_date', '2026-03-09')
            ->assertJsonPath('data.items.0.exercise', 'Bench Press');
    }

    private function createOwnerWorkspaceContext(): array
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        return [$owner, $workspace];
    }
}
