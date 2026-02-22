<?php

namespace Tests\Feature\Api\V1\Appointment;

use App\Models\AppointmentSeries;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentSeriesDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_series_detail(): void
    {
        [$owner, $workspace, $student, $series] = $this->seedContext();

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/v1/appointments/series/{$series->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $series->id);
    }

    public function test_owner_can_pause_series(): void
    {
        [$owner, $workspace, $student, $series] = $this->seedContext();

        Sanctum::actingAs($owner);

        $response = $this->patchJson("/api/v1/appointments/series/{$series->id}/status", [
            'status' => AppointmentSeries::STATUS_PAUSED,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', AppointmentSeries::STATUS_PAUSED);
    }

    public function test_owner_can_end_series(): void
    {
        [$owner, $workspace, $student, $series] = $this->seedContext();

        Sanctum::actingAs($owner);

        $response = $this->patchJson("/api/v1/appointments/series/{$series->id}/status", [
            'status' => AppointmentSeries::STATUS_ENDED,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', AppointmentSeries::STATUS_ENDED);
    }

    public function test_user_from_other_workspace_cannot_view_series(): void
    {
        [$owner, $workspace, $student, $series] = $this->seedContext();

        $otherOwner = User::factory()->ownerAdmin()->create();
        $otherWorkspace = Workspace::factory()->create(['owner_user_id' => $otherOwner->id]);
        $otherWorkspace->users()->attach($otherOwner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $otherOwner->update(['active_workspace_id' => $otherWorkspace->id]);

        Sanctum::actingAs($otherOwner);

        $response = $this->getJson("/api/v1/appointments/series/{$series->id}");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /**
     * @return array{0: User, 1: Workspace, 2: Student, 3: AppointmentSeries}
     */
    private function seedContext(): array
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
        ]);

        $series = AppointmentSeries::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'status' => AppointmentSeries::STATUS_ACTIVE,
        ]);

        return [$owner, $workspace, $student, $series];
    }
}
