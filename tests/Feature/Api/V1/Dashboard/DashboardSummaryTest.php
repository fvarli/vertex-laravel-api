<?php

namespace Tests\Feature\Api\V1\Dashboard;

use App\Models\Appointment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_gets_workspace_wide_summary(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainer = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $studentActive = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'status' => Student::STATUS_ACTIVE,
        ]);

        Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'status' => Student::STATUS_PASSIVE,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $studentActive->id,
            'starts_at' => now()->startOfDay()->addHours(2),
            'ends_at' => now()->startOfDay()->addHours(3),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Program::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $studentActive->id,
            'trainer_user_id' => $trainer->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'status' => Program::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.students.total', 2)
            ->assertJsonPath('data.students.active', 1)
            ->assertJsonPath('data.programs.active_this_week', 1);
    }

    public function test_trainer_gets_only_self_scoped_summary(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainerA = User::factory()->trainer()->create();
        $trainerB = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainerA->id, ['role' => 'trainer', 'is_active' => true]);
        $workspace->users()->attach($trainerB->id, ['role' => 'trainer', 'is_active' => true]);
        $trainerA->update(['active_workspace_id' => $workspace->id]);

        $studentA = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerA->id,
            'status' => Student::STATUS_ACTIVE,
        ]);

        Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerB->id,
            'status' => Student::STATUS_ACTIVE,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerA->id,
            'student_id' => $studentA->id,
            'starts_at' => now()->startOfDay()->addHours(2),
            'ends_at' => now()->startOfDay()->addHours(3),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Sanctum::actingAs($trainerA);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.students.total', 1)
            ->assertJsonPath('data.appointments.today_total', 1);
    }
}
