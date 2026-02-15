<?php

namespace Tests\Feature\Api\V1\Report;

use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsStudentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_report_returns_status_breakdown_and_buckets(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'status' => Student::STATUS_ACTIVE,
            'created_at' => '2026-06-05 10:00:00',
        ]);

        Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'status' => Student::STATUS_PASSIVE,
            'created_at' => '2026-06-07 10:00:00',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/students?date_from=2026-06-01&date_to=2026-06-30&group_by=week');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.total', 2)
            ->assertJsonPath('data.totals.active', 1)
            ->assertJsonPath('data.totals.passive', 1)
            ->assertJsonPath('data.filters.group_by', 'week');
    }

    public function test_students_report_rejects_invalid_group_by(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/students?group_by=year');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['group_by']);
    }
}
