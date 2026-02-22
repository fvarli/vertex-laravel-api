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

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $studentActive->id,
            'starts_at' => now()->startOfDay()->addHours(4),
            'ends_at' => now()->startOfDay()->addHours(5),
            'status' => Appointment::STATUS_DONE,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $studentActive->id,
            'starts_at' => now()->startOfDay()->addHours(6),
            'ends_at' => now()->startOfDay()->addHours(7),
            'status' => Appointment::STATUS_NO_SHOW,
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
            ->assertJsonPath('data.programs.active_this_week', 1)
            ->assertJsonPath('data.appointments.today_no_show', 1)
            ->assertJsonPath('data.appointments.today_attendance_rate', 50)
            ->assertJsonStructure([
                'data' => [
                    'trends' => ['appointments_vs_last_week', 'new_students_this_month', 'completion_rate_trend'],
                    'top_trainers',
                ],
            ]);

        $this->assertIsArray($response->json('data.top_trainers'));
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
            ->assertJsonPath('data.appointments.today_total', 1)
            ->assertJsonStructure([
                'data' => [
                    'trends' => ['appointments_vs_last_week', 'new_students_this_month', 'completion_rate_trend'],
                ],
            ]);

        // Trainer scoped view returns empty top_trainers
        $this->assertEmpty($response->json('data.top_trainers'));
    }

    public function test_top_trainers_shows_leaderboard(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainerA = User::factory()->trainer()->create(['name' => 'Alice', 'surname' => 'Top']);
        $trainerB = User::factory()->trainer()->create(['name' => 'Bob', 'surname' => 'Low']);
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainerA->id, ['role' => 'trainer', 'is_active' => true]);
        $workspace->users()->attach($trainerB->id, ['role' => 'trainer', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerA->id,
            'status' => Student::STATUS_ACTIVE,
        ]);

        // TrainerA has 2 completed sessions this week
        Appointment::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerA->id,
            'student_id' => $student->id,
            'starts_at' => now()->startOfWeek()->addHours(10),
            'ends_at' => now()->startOfWeek()->addHours(11),
            'status' => Appointment::STATUS_DONE,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk();

        $topTrainers = $response->json('data.top_trainers');
        $this->assertNotEmpty($topTrainers);
        $this->assertEquals('Alice Top', $topTrainers[0]['name']);
        $this->assertEquals(2, $topTrainers[0]['completed_sessions']);
    }

    public function test_trends_show_comparison_data(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk();

        $trends = $response->json('data.trends');
        $this->assertArrayHasKey('appointments_vs_last_week', $trends);
        $this->assertArrayHasKey('new_students_this_month', $trends);
        $this->assertArrayHasKey('completion_rate_trend', $trends);
        $this->assertContains($trends['completion_rate_trend'], ['up', 'down', 'stable']);
    }
}
