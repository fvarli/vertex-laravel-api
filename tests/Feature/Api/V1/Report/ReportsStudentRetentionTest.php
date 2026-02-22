<?php

namespace Tests\Feature\Api\V1\Report;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsStudentRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_can_view_student_retention(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainer = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'status' => Student::STATUS_ACTIVE,
            'created_at' => '2026-06-05 10:00:00',
        ]);

        Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'status' => Student::STATUS_PASSIVE,
            'created_at' => '2026-05-01 10:00:00',
            'updated_at' => '2026-06-10 10:00:00',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/student-retention?date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_students', 2)
            ->assertJsonPath('data.new_students', 1)
            ->assertJsonPath('data.churned_students', 1);

        $this->assertArrayHasKey('retention_rate', $response->json('data'));
        $this->assertArrayHasKey('churn_rate', $response->json('data'));
        $this->assertArrayHasKey('avg_student_lifetime_days', $response->json('data'));
        $this->assertArrayHasKey('students_without_appointment_30d', $response->json('data'));
    }

    public function test_trainer_cannot_access_student_retention(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainer = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $trainer->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($trainer);

        $response = $this->getJson('/api/v1/reports/student-retention?date_from=2026-06-01&date_to=2026-06-30');

        $response->assertStatus(403);
    }

    public function test_empty_workspace_returns_zero_defaults(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/student-retention?date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk()
            ->assertJsonPath('data.total_students', 0)
            ->assertJsonPath('data.new_students', 0)
            ->assertJsonPath('data.churned_students', 0)
            ->assertJsonPath('data.retention_rate', 0)
            ->assertJsonPath('data.churn_rate', 0);
    }
}
