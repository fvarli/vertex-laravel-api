<?php

namespace Tests\Feature\Api\V1\Report;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsProgramsTest extends TestCase
{
    use RefreshDatabase;

    public function test_programs_report_returns_status_totals(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
        ]);

        Program::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $student->id,
            'trainer_user_id' => $owner->id,
            'week_start_date' => '2026-06-02',
            'status' => Program::STATUS_DRAFT,
        ]);

        Program::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $student->id,
            'trainer_user_id' => $owner->id,
            'week_start_date' => '2026-06-09',
            'status' => Program::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/programs?date_from=2026-06-01&date_to=2026-06-30&group_by=month');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.total', 2)
            ->assertJsonPath('data.totals.draft', 1)
            ->assertJsonPath('data.totals.active', 1);
    }
}
