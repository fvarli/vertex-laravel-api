<?php

namespace Tests\Feature\Api\V1\Appointment;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlapping_appointment_returns_422_with_conflict_code(): void
    {
        $trainer = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $trainer->id]);
        $workspace->users()->attach($trainer->id, ['role' => 'owner_admin', 'is_active' => true]);
        $trainer->update(['active_workspace_id' => $workspace->id]);

        $studentA = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'phone' => '+905551111111',
        ]);

        $studentB = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'phone' => '+905552222222',
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $studentA->id,
            'starts_at' => '2026-02-20 10:00:00',
            'ends_at' => '2026-02-20 11:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Sanctum::actingAs($trainer);

        $response = $this->postJson('/api/v1/appointments', [
            'student_id' => $studentB->id,
            'starts_at' => '2026-02-20 10:30:00',
            'ends_at' => '2026-02-20 11:30:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code.0', 'time_slot_conflict');
    }
}
