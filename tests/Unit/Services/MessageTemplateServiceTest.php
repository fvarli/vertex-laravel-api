<?php

namespace Tests\Unit\Services;

use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MessageTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private MessageTemplateService $service;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MessageTemplateService;

        $owner = User::factory()->ownerAdmin()->create();
        $this->workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
    }

    public function test_list_returns_templates_for_workspace(): void
    {
        MessageTemplate::factory()->count(2)->create(['workspace_id' => $this->workspace->id]);

        $result = $this->service->list($this->workspace->id);

        $this->assertCount(2, $result);
    }

    public function test_create_returns_template(): void
    {
        $template = $this->service->create($this->workspace->id, [
            'name' => 'Welcome',
            'body' => 'Hello {name}',
            'channel' => 'whatsapp',
        ]);

        $this->assertEquals('Welcome', $template->name);
        $this->assertEquals($this->workspace->id, $template->workspace_id);
    }

    public function test_create_with_is_default_resets_others(): void
    {
        $existing = $this->service->create($this->workspace->id, [
            'name' => 'Old Default',
            'body' => 'Old body',
            'is_default' => true,
        ]);

        $this->service->create($this->workspace->id, [
            'name' => 'New Default',
            'body' => 'New body',
            'is_default' => true,
        ]);

        $this->assertFalse((bool) $existing->fresh()->is_default);
    }

    public function test_update_modifies_template(): void
    {
        $template = $this->service->create($this->workspace->id, [
            'name' => 'Original',
            'body' => 'Original body',
        ]);

        $updated = $this->service->update($template, ['name' => 'Updated']);

        $this->assertEquals('Updated', $updated->name);
    }

    public function test_update_with_is_default_resets_others(): void
    {
        $first = $this->service->create($this->workspace->id, [
            'name' => 'First',
            'body' => 'First body',
            'is_default' => true,
        ]);

        $second = $this->service->create($this->workspace->id, [
            'name' => 'Second',
            'body' => 'Second body',
        ]);

        $this->service->update($second, ['is_default' => true]);

        $this->assertFalse((bool) $first->fresh()->is_default);
        $this->assertTrue((bool) $second->fresh()->is_default);
    }

    public function test_delete_removes_template(): void
    {
        $template = $this->service->create($this->workspace->id, [
            'name' => 'ToDelete',
            'body' => 'Delete me',
        ]);

        $this->service->delete($template);

        $this->assertNull(MessageTemplate::find($template->id));
    }
}
