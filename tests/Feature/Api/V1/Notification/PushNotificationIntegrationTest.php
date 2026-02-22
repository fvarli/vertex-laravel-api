<?php

namespace Tests\Feature\Api\V1\Notification;

use App\Channels\FcmChannel;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceApprovalRequestedNotification;
use App\Notifications\WorkspaceApprovedNotification;
use App\Notifications\WorkspaceRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_approval_requested_includes_fcm_when_device_exists(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        DeviceToken::factory()->create(['user_id' => $admin->id, 'is_active' => true]);

        $workspace = Workspace::factory()->create();
        $owner = User::factory()->verifiedActive()->create();
        $notification = new WorkspaceApprovalRequestedNotification($workspace, $owner);

        $channels = $notification->via($admin);

        $this->assertContains(FcmChannel::class, $channels);
        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_workspace_approval_requested_excludes_fcm_without_device(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $workspace = Workspace::factory()->create();
        $owner = User::factory()->verifiedActive()->create();
        $notification = new WorkspaceApprovalRequestedNotification($workspace, $owner);

        $channels = $notification->via($admin);

        $this->assertNotContains(FcmChannel::class, $channels);
    }

    public function test_workspace_approval_requested_has_fcm_payload(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->verifiedActive()->create(['email' => 'owner@test.com']);
        $workspace = Workspace::factory()->create(['name' => 'Test Gym']);

        $notification = new WorkspaceApprovalRequestedNotification($workspace, $owner);
        $payload = $notification->toFcm($admin);

        $this->assertEquals('New workspace approval request', $payload['title']);
        $this->assertStringContains('Test Gym', $payload['body']);
        $this->assertEquals('workspace.approval_requested', $payload['data']['type']);
    }

    public function test_workspace_approved_includes_fcm_when_device_exists(): void
    {
        $user = User::factory()->verifiedActive()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        $workspace = Workspace::factory()->create(['name' => 'Approved Gym']);
        $notification = new WorkspaceApprovedNotification($workspace);

        $channels = $notification->via($user);
        $this->assertContains(FcmChannel::class, $channels);

        $payload = $notification->toFcm($user);
        $this->assertEquals('Workspace approved', $payload['title']);
        $this->assertStringContains('Approved Gym', $payload['body']);
    }

    public function test_workspace_rejected_includes_fcm_when_device_exists(): void
    {
        $user = User::factory()->verifiedActive()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        $workspace = Workspace::factory()->create(['name' => 'Rejected Gym']);
        $notification = new WorkspaceRejectedNotification($workspace);

        $channels = $notification->via($user);
        $this->assertContains(FcmChannel::class, $channels);

        $payload = $notification->toFcm($user);
        $this->assertEquals('Workspace rejected', $payload['title']);
        $this->assertStringContains('Rejected Gym', $payload['body']);
    }

    public function test_inactive_device_token_does_not_trigger_fcm(): void
    {
        $user = User::factory()->verifiedActive()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'is_active' => false]);

        $workspace = Workspace::factory()->create();
        $notification = new WorkspaceApprovedNotification($workspace);

        $channels = $notification->via($user);
        $this->assertNotContains(FcmChannel::class, $channels);
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '$haystack' contains '$needle'.",
        );
    }
}
