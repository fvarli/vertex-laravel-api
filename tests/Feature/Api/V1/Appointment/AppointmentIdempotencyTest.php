<?php

namespace Tests\Feature\Api\V1\Appointment;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_idempotency_key_and_same_payload_returns_same_response(): void
    {
        [$owner, $workspace, $student] = $this->createContext();
        Sanctum::actingAs($owner);

        $payload = [
            'student_id' => $student->id,
            'starts_at' => '2026-05-01 10:00:00',
            'ends_at' => '2026-05-01 11:00:00',
            'location' => 'Gym North',
        ];

        $first = $this->postJson('/api/v1/appointments', $payload, ['Idempotency-Key' => 'appt-key-001']);
        $second = $this->postJson('/api/v1/appointments', $payload, ['Idempotency-Key' => 'appt-key-001']);

        $first->assertStatus(201)->assertJsonPath('success', true);
        $second->assertStatus(201)->assertJsonPath('success', true);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_same_idempotency_key_with_different_payload_returns_422(): void
    {
        [$owner, , $student] = $this->createContext();
        Sanctum::actingAs($owner);

        $firstPayload = [
            'student_id' => $student->id,
            'starts_at' => '2026-05-02 10:00:00',
            'ends_at' => '2026-05-02 11:00:00',
        ];

        $secondPayload = [
            'student_id' => $student->id,
            'starts_at' => '2026-05-02 12:00:00',
            'ends_at' => '2026-05-02 13:00:00',
        ];

        $this->postJson('/api/v1/appointments', $firstPayload, ['Idempotency-Key' => 'appt-key-002'])
            ->assertStatus(201);

        $response = $this->postJson('/api/v1/appointments', $secondPayload, ['Idempotency-Key' => 'appt-key-002']);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code.0', 'idempotency_payload_mismatch');
    }

    private function createContext(): array
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
        ]);

        return [$owner, $workspace, $student];
    }
}
