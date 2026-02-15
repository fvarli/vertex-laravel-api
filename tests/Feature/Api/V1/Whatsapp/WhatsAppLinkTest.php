<?php

namespace Tests\Feature\Api\V1\Whatsapp;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_whatsapp_link_is_generated(): void
    {
        $trainer = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $trainer->id]);
        $workspace->users()->attach($trainer->id, ['role' => 'owner_admin', 'is_active' => true]);
        $trainer->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'full_name' => 'Test Student',
            'phone' => '+90 555 123 45 67',
        ]);

        Sanctum::actingAs($trainer);

        $response = $this->getJson("/api/v1/students/{$student->id}/whatsapp-link?template=reminder");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $url = (string) $response->json('data.url');

        $this->assertStringStartsWith('https://wa.me/905551234567?text=', $url);
    }

    public function test_appointment_whatsapp_link_is_generated(): void
    {
        $trainer = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $trainer->id]);
        $workspace->users()->attach($trainer->id, ['role' => 'owner_admin', 'is_active' => true]);
        $trainer->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'full_name' => 'Test Student',
            'phone' => '+90 555 123 45 67',
        ]);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-07-12 11:00:00',
            'ends_at' => '2026-07-12 12:00:00',
        ]);

        Sanctum::actingAs($trainer);

        $response = $this->getJson("/api/v1/appointments/{$appointment->id}/whatsapp-link?template=reminder");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $url = (string) $response->json('data.url');
        $this->assertStringStartsWith('https://wa.me/905551234567?text=', $url);
    }
}
