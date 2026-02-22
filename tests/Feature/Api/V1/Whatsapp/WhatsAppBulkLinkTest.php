<?php

namespace Tests\Feature\Api\V1\Whatsapp;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppBulkLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $trainer;

    private Workspace $workspace;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->ownerAdmin()->create();
        $this->trainer = User::factory()->trainer()->create();
        $this->workspace = Workspace::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->workspace->users()->attach($this->owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $this->workspace->users()->attach($this->trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $this->owner->update(['active_workspace_id' => $this->workspace->id]);

        $this->student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_ACTIVE,
            'phone' => '+905551234567',
        ]);
    }

    public function test_bulk_links_returns_appointments_for_date(): void
    {
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-07-10 10:00:00',
            'ends_at' => '2026-07-10 11:00:00',
            'status' => Appointment::STATUS_PLANNED,
            'whatsapp_status' => Appointment::WHATSAPP_STATUS_NOT_SENT,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/whatsapp/bulk-links?date=2026-07-10');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('appointment_id', $data[0]);
        $this->assertArrayHasKey('student_name', $data[0]);
        $this->assertArrayHasKey('whatsapp_link', $data[0]);
        $this->assertArrayHasKey('whatsapp_status', $data[0]);
        $this->assertStringContainsString('wa.me', $data[0]['whatsapp_link']);
    }

    public function test_bulk_links_excludes_cancelled_appointments(): void
    {
        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-07-10 10:00:00',
            'ends_at' => '2026-07-10 11:00:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/whatsapp/bulk-links?date=2026-07-10');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_trainer_only_sees_own_appointments(): void
    {
        $otherTrainer = User::factory()->trainer()->create();
        $this->workspace->users()->attach($otherTrainer->id, ['role' => 'trainer', 'is_active' => true]);

        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
            'starts_at' => '2026-07-10 10:00:00',
            'ends_at' => '2026-07-10 11:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        $otherTrainer->update(['active_workspace_id' => $this->workspace->id]);
        Sanctum::actingAs($otherTrainer);

        $response = $this->getJson('/api/v1/whatsapp/bulk-links?date=2026-07-10');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_bulk_links_requires_date_parameter(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/whatsapp/bulk-links');

        $response->assertStatus(422);
    }

    public function test_empty_date_returns_empty_array(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/whatsapp/bulk-links?date=2030-01-01');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_unauthenticated_cannot_access_bulk_links(): void
    {
        $response = $this->getJson('/api/v1/whatsapp/bulk-links?date=2026-07-10');

        $response->assertStatus(401);
    }
}
