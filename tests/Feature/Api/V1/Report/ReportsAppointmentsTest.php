<?php

namespace Tests\Feature\Api\V1\Report;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_can_view_workspace_appointment_report(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainer = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/appointments?date_from=2026-06-01&date_to=2026-06-30&group_by=day');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.total', 1)
            ->assertJsonPath('data.totals.done', 1)
            ->assertJsonPath('data.filters.group_by', 'day');
    }

    public function test_trainer_scope_is_self_even_if_trainer_id_query_sent(): void
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
        ]);
        $studentB = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerB->id,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerA->id,
            'student_id' => $studentA->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerB->id,
            'student_id' => $studentB->id,
            'starts_at' => '2026-06-10 12:00:00',
            'ends_at' => '2026-06-10 13:00:00',
        ]);

        Sanctum::actingAs($trainerA);

        $response = $this->getJson("/api/v1/reports/appointments?date_from=2026-06-01&date_to=2026-06-30&trainer_id={$trainerB->id}");

        $response->assertOk()
            ->assertJsonPath('data.totals.total', 1);
    }
}
