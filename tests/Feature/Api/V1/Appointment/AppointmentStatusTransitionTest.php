<?php

namespace Tests\Feature\Api\V1\Appointment;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_done_to_cancelled_transition_is_rejected(): void
    {
        [$owner, $appointment] = $this->createContextWithAppointment(Appointment::STATUS_DONE, now()->subHour());

        Sanctum::actingAs($owner);

        $response = $this->patchJson("/api/v1/appointments/{$appointment->id}/status", [
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_planned_to_done_is_rejected_for_future_appointment(): void
    {
        [$owner, $appointment] = $this->createContextWithAppointment(Appointment::STATUS_PLANNED, now()->addDay());

        Sanctum::actingAs($owner);

        $response = $this->patchJson("/api/v1/appointments/{$appointment->id}/status", [
            'status' => Appointment::STATUS_DONE,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cancelled_to_planned_transition_is_allowed(): void
    {
        [$owner, $appointment] = $this->createContextWithAppointment(Appointment::STATUS_CANCELLED, now()->subDay());

        Sanctum::actingAs($owner);

        $response = $this->patchJson("/api/v1/appointments/{$appointment->id}/status", [
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Appointment::STATUS_PLANNED);
    }

    private function createContextWithAppointment(string $status, \Carbon\CarbonInterface $startsAt): array
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
        ]);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHour(),
            'status' => $status,
        ]);

        return [$owner, $appointment];
    }
}
