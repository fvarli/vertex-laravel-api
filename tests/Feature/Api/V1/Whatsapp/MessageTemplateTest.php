<?php

namespace Tests\Feature\Api\V1\Whatsapp;

use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $trainer;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->ownerAdmin()->create();
        $this->trainer = User::factory()->trainer()->create();
        $this->workspace = Workspace::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->workspace->users()->attach($this->owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $this->workspace->users()->attach($this->trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $this->owner->update(['active_workspace_id' => $this->workspace->id]);
    }

    public function test_owner_can_create_template(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/message-templates', [
            'name' => 'Appointment Reminder',
            'body' => 'Hi {student_name}, your session is on {appointment_date} at {appointment_time}.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Appointment Reminder')
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.is_default', false);
    }

    public function test_owner_can_create_default_template(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/message-templates', [
            'name' => 'Default Template',
            'body' => 'Hi {student_name}!',
            'is_default' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_default', true);
    }

    public function test_setting_default_unsets_previous_default(): void
    {
        $existing = MessageTemplate::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_default' => true,
        ]);

        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/message-templates', [
            'name' => 'New Default',
            'body' => 'Hello {student_name}!',
            'is_default' => true,
        ])->assertStatus(201);

        $this->assertFalse($existing->fresh()->is_default);
    }

    public function test_trainer_cannot_create_template(): void
    {
        $this->trainer->update(['active_workspace_id' => $this->workspace->id]);
        Sanctum::actingAs($this->trainer);

        $response = $this->postJson('/api/v1/message-templates', [
            'name' => 'Test',
            'body' => 'Hi {student_name}',
        ]);

        $response->assertStatus(403);
    }

    public function test_list_templates(): void
    {
        MessageTemplate::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/message-templates');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_owner_can_update_template(): void
    {
        $template = MessageTemplate::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Old Name',
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/message-templates/{$template->id}", [
            'name' => 'New Name',
            'body' => 'Updated body for {student_name}',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_owner_can_delete_template(): void
    {
        $template = MessageTemplate::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->deleteJson("/api/v1/message-templates/{$template->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('message_templates', ['id' => $template->id]);
    }

    public function test_cannot_access_other_workspace_template(): void
    {
        $otherWorkspace = Workspace::factory()->create();
        $template = MessageTemplate::factory()->create([
            'workspace_id' => $otherWorkspace->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/message-templates/{$template->id}", [
            'name' => 'Hack',
        ]);

        $response->assertStatus(404);
    }

    public function test_validation_requires_name_and_body(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/message-templates', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'body']);
    }

    public function test_template_render_replaces_placeholders(): void
    {
        $template = MessageTemplate::factory()->create([
            'workspace_id' => $this->workspace->id,
            'body' => 'Hi {student_name}, session on {appointment_date} at {appointment_time} with {trainer_name}.',
        ]);

        $rendered = $template->render([
            'student_name' => 'John',
            'appointment_date' => '2026-07-10',
            'appointment_time' => '10:00',
            'trainer_name' => 'Alice',
        ]);

        $this->assertEquals('Hi John, session on 2026-07-10 at 10:00 with Alice.', $rendered);
    }
}
