<?php

namespace Tests\Feature\Api\V1\Workspace;

use App\Models\User;
use App\Notifications\WorkspaceApprovalRequestedNotification;
use App\Notifications\WorkspaceApprovedNotification;
use App\Notifications\WorkspaceRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_creation_sends_approval_request_notification_to_platform_admins(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $platformAdminA = User::factory()->platformAdmin()->create();
        $platformAdminB = User::factory()->platformAdmin()->create();

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/workspaces', [
            'name' => 'Notify Studio',
        ])->assertStatus(201);

        Notification::assertSentTo([$platformAdminA, $platformAdminB], WorkspaceApprovalRequestedNotification::class);
    }

    public function test_approval_and_rejection_send_decision_notifications_to_owner(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $platformAdmin = User::factory()->platformAdmin()->create();

        Sanctum::actingAs($owner);
        $workspaceId = (int) $this->postJson('/api/v1/workspaces', [
            'name' => 'Decision Studio',
        ])->json('data.id');

        Sanctum::actingAs($platformAdmin);
        $this->patchJson("/api/v1/platform/workspaces/{$workspaceId}/approval", [
            'approval_status' => 'approved',
        ])->assertStatus(200);

        Notification::assertSentTo($owner, WorkspaceApprovedNotification::class);

        $this->patchJson("/api/v1/platform/workspaces/{$workspaceId}/approval", [
            'approval_status' => 'rejected',
            'approval_note' => 'Missing business details',
        ])->assertStatus(200);

        Notification::assertSentTo($owner, WorkspaceRejectedNotification::class);
    }
}
