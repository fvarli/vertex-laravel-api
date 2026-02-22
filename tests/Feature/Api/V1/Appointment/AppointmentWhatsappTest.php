<?php

namespace Tests\Feature\Api\V1\Appointment;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentWhatsappTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_mark_whatsapp_as_sent(): void
    {
        [$owner, $workspace, $student, $appointment] = $this->seedContext();

        Sanctum::actingAs($owner);

        $response = $this->patchJson("/api/v1/appointments/{$appointment->id}/whatsapp-status", [
            'whatsapp_status' => 'sent',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.whatsapp_status', 'sent')
            ->assertJsonPath('data.whatsapp_marked_by_user_id', $owner->id);

        $appointment->refresh();
        $this->assertNotNull($appointment->whatsapp_marked_at);
        $this->assertEquals($owner->id, $appointment->whatsapp_marked_by_user_id);
    }

    public function test_owner_can_mark_whatsapp_as_not_sent(): void
    {
        [$owner, $workspace, $student, $appointment] = $this->seedContext();

        Sanctum::actingAs($owner);

        // First mark as sent
        $this->patchJson("/api/v1/appointments/{$appointment->id}/whatsapp-status", [
            'whatsapp_status' => 'sent',
        ])->assertOk();

        $appointment->refresh();
        $this->assertNotNull($appointment->whatsapp_marked_at);

        // Now mark as not_sent
        $response = $this->patchJson("/api/v1/appointments/{$appointment->id}/whatsapp-status", [
            'whatsapp_status' => 'not_sent',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.whatsapp_status', 'not_sent')
            ->assertJsonPath('data.whatsapp_marked_at', null)
            ->assertJsonPath('data.whatsapp_marked_by_user_id', null);

        $appointment->refresh();
        $this->assertNull($appointment->whatsapp_marked_at);
        $this->assertNull($appointment->whatsapp_marked_by_user_id);
    }

    public function test_whatsapp_status_validation_rejects_invalid_value(): void
    {
        [$owner, $workspace, $student, $appointment] = $this->seedContext();

        Sanctum::actingAs($owner);

        $response = $this->patchJson("/api/v1/appointments/{$appointment->id}/whatsapp-status", [
            'whatsapp_status' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['whatsapp_status']);
    }

    public function test_trainer_from_other_workspace_cannot_update_whatsapp_status(): void
    {
        [$owner, $workspace, $student, $appointment] = $this->seedContext();

        $otherUser = User::factory()->ownerAdmin()->create();
        $otherWorkspace = Workspace::factory()->create(['owner_user_id' => $otherUser->id]);
        $otherWorkspace->users()->attach($otherUser->id, ['role' => 'owner_admin', 'is_active' => true]);
        $otherUser->update(['active_workspace_id' => $otherWorkspace->id]);

        Sanctum::actingAs($otherUser);

        $response = $this->patchJson("/api/v1/appointments/{$appointment->id}/whatsapp-status", [
            'whatsapp_status' => 'sent',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

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

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        return [$owner, $workspace, $student, $appointment];
    }
}
