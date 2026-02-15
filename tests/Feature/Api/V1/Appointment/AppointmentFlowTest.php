<?php

namespace Tests\Feature\Api\V1\Appointment;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_supports_date_from_date_to_and_status_filters(): void
    {
        [$owner, $workspace] = $this->createOwnerWorkspaceContext();
        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'phone' => '+905553333333',
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => '2026-02-20 09:00:00',
            'ends_at' => '2026-02-20 10:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => '2026-02-21 09:00:00',
            'ends_at' => '2026-02-21 10:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/appointments?date_from=2026-02-20 00:00:00&date_to=2026-02-20 23:59:59&status=done');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.status', Appointment::STATUS_DONE);
    }

    public function test_calendar_endpoint_returns_grouped_days_and_excludes_cancelled(): void
    {
        [$owner, $workspace] = $this->createOwnerWorkspaceContext();
        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'phone' => '+905554444444',
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => '2026-02-22 11:00:00',
            'ends_at' => '2026-02-22 12:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => '2026-02-22 13:00:00',
            'ends_at' => '2026-02-22 14:00:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/calendar?from=2026-02-22 00:00:00&to=2026-02-22 23:59:59');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.appointments')
            ->assertJsonCount(1, 'data.days')
            ->assertJsonPath('data.days.0.date', '2026-02-22')
            ->assertJsonCount(1, 'data.days.0.items');
    }

    public function test_trainer_cannot_create_appointment_for_another_trainers_student(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainerA = User::factory()->trainer()->create();
        $trainerB = User::factory()->trainer()->create();

        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainerA->id, ['role' => 'trainer', 'is_active' => true]);
        $workspace->users()->attach($trainerB->id, ['role' => 'trainer', 'is_active' => true]);
        $trainerA->update(['active_workspace_id' => $workspace->id]);

        $studentForTrainerB = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerB->id,
            'phone' => '+905555555555',
        ]);

        Sanctum::actingAs($trainerA);

        $response = $this->postJson('/api/v1/appointments', [
            'student_id' => $studentForTrainerB->id,
            'starts_at' => '2026-02-23 10:00:00',
            'ends_at' => '2026-02-23 11:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['student_id']);
    }

    public function test_appointments_index_supports_search_sort_and_direction_contract(): void
    {
        [$owner, $workspace] = $this->createOwnerWorkspaceContext();
        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'full_name' => 'Ceren Kaya',
            'phone' => '+905556666666',
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'location' => 'Gym A',
            'starts_at' => '2026-03-01 09:00:00',
            'ends_at' => '2026-03-01 10:00:00',
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'location' => 'Gym B',
            'starts_at' => '2026-03-01 11:00:00',
            'ends_at' => '2026-03-01 12:00:00',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/appointments?search=Gym%20A&sort=starts_at&direction=asc');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.location', 'Gym A');
    }

    public function test_appointment_whatsapp_status_can_be_manually_toggled_and_filtered(): void
    {
        [$owner, $workspace] = $this->createOwnerWorkspaceContext();
        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'phone' => '+905556666123',
        ]);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'whatsapp_status' => Appointment::WHATSAPP_STATUS_NOT_SENT,
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/appointments/{$appointment->id}/whatsapp-status", [
            'whatsapp_status' => Appointment::WHATSAPP_STATUS_SENT,
        ])->assertOk()
            ->assertJsonPath('data.whatsapp_status', Appointment::WHATSAPP_STATUS_SENT)
            ->assertJsonPath('data.whatsapp_marked_by_user_id', $owner->id);

        $appointment->refresh();
        $this->assertNotNull($appointment->whatsapp_marked_at);

        $filteredSent = $this->getJson('/api/v1/appointments?whatsapp_status=sent');
        $filteredSent->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $appointment->id);

        $this->patchJson("/api/v1/appointments/{$appointment->id}/whatsapp-status", [
            'whatsapp_status' => Appointment::WHATSAPP_STATUS_NOT_SENT,
        ])->assertOk()
            ->assertJsonPath('data.whatsapp_status', Appointment::WHATSAPP_STATUS_NOT_SENT)
            ->assertJsonPath('data.whatsapp_marked_at', null)
            ->assertJsonPath('data.whatsapp_marked_by_user_id', null);
    }

    public function test_trainer_cannot_set_trainer_user_id_on_store_or_update(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainerA = User::factory()->trainer()->create();
        $trainerB = User::factory()->trainer()->create();

        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainerA->id, ['role' => 'trainer', 'is_active' => true]);
        $workspace->users()->attach($trainerB->id, ['role' => 'trainer', 'is_active' => true]);
        $trainerA->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerA->id,
            'phone' => '+905557777777',
        ]);

        Sanctum::actingAs($trainerA);

        $storeResponse = $this->postJson('/api/v1/appointments', [
            'student_id' => $student->id,
            'trainer_user_id' => $trainerB->id,
            'starts_at' => '2026-04-01 10:00:00',
            'ends_at' => '2026-04-01 11:00:00',
        ]);

        $storeResponse->assertStatus(403)
            ->assertJsonPath('success', false);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerA->id,
            'student_id' => $student->id,
            'starts_at' => '2026-04-02 10:00:00',
            'ends_at' => '2026-04-02 11:00:00',
        ]);

        $updateResponse = $this->putJson("/api/v1/appointments/{$appointment->id}", [
            'trainer_user_id' => $trainerB->id,
        ]);

        $updateResponse->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    private function createOwnerWorkspaceContext(): array
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        return [$owner, $workspace];
    }
}
