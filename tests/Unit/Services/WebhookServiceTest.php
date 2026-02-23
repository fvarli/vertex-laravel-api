<?php

namespace Tests\Unit\Services;

use App\Jobs\DispatchWebhookJob;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\Workspace;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebhookService;
    }

    public function test_dispatches_to_subscribed_endpoints(): void
    {
        Queue::fake();

        $owner = User::factory()->verifiedActive()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        WebhookEndpoint::create([
            'workspace_id' => $workspace->id,
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'events' => ['appointment.created'],
            'is_active' => true,
        ]);

        $count = $this->service->dispatch($workspace->id, 'appointment.created', ['id' => 1]);

        $this->assertEquals(1, $count);
        Queue::assertPushed(DispatchWebhookJob::class);
    }

    public function test_skips_unsubscribed_events(): void
    {
        Queue::fake();

        $owner = User::factory()->verifiedActive()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        WebhookEndpoint::create([
            'workspace_id' => $workspace->id,
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'events' => ['student.created'],
            'is_active' => true,
        ]);

        $count = $this->service->dispatch($workspace->id, 'appointment.created', ['id' => 1]);

        $this->assertEquals(0, $count);
        Queue::assertNothingPushed();
    }

    public function test_wildcard_event_subscribes_to_all(): void
    {
        Queue::fake();

        $owner = User::factory()->verifiedActive()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        WebhookEndpoint::create([
            'workspace_id' => $workspace->id,
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'events' => ['*'],
            'is_active' => true,
        ]);

        $count = $this->service->dispatch($workspace->id, 'appointment.created', ['id' => 1]);

        $this->assertEquals(1, $count);
        Queue::assertPushed(DispatchWebhookJob::class);
    }

    public function test_skips_inactive_endpoints(): void
    {
        Queue::fake();

        $owner = User::factory()->verifiedActive()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        WebhookEndpoint::create([
            'workspace_id' => $workspace->id,
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'events' => ['appointment.created'],
            'is_active' => false,
        ]);

        $count = $this->service->dispatch($workspace->id, 'appointment.created', ['id' => 1]);

        $this->assertEquals(0, $count);
        Queue::assertNothingPushed();
    }

    // ── CRUD ──────────────────────────────────────────────────

    public function test_list_returns_webhooks_for_workspace(): void
    {
        $owner = User::factory()->verifiedActive()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        WebhookEndpoint::create([
            'workspace_id' => $workspace->id,
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'events' => ['appointment.created'],
            'is_active' => true,
        ]);

        $result = $this->service->list($workspace->id);

        $this->assertCount(1, $result);
        $this->assertEquals('https://example.com/hook', $result->first()['url']);
    }

    public function test_create_returns_webhook_with_secret(): void
    {
        $owner = User::factory()->verifiedActive()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $webhook = $this->service->create($workspace->id, 'https://example.com/hook', ['appointment.created']);

        $this->assertEquals($workspace->id, $webhook->workspace_id);
        $this->assertEquals('https://example.com/hook', $webhook->url);
        $this->assertNotEmpty($webhook->secret);
        $this->assertTrue($webhook->is_active);
    }

    public function test_update_modifies_webhook(): void
    {
        $owner = User::factory()->verifiedActive()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $webhook = $this->service->create($workspace->id, 'https://example.com/hook', ['appointment.created']);
        $updated = $this->service->update($webhook, ['is_active' => false]);

        $this->assertFalse($updated->is_active);
    }

    public function test_delete_removes_webhook(): void
    {
        $owner = User::factory()->verifiedActive()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);

        $webhook = $this->service->create($workspace->id, 'https://example.com/hook', ['appointment.created']);
        $this->service->delete($webhook);

        $this->assertNull(WebhookEndpoint::find($webhook->id));
    }
}
