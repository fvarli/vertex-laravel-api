<?php

namespace Tests\Feature\Api\V1\Notification;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_only_filter_returns_only_unread_notifications(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'approval_status' => 'approved',
        ]);

        DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\WorkspaceApprovedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'type' => 'workspace.approved',
                'workspace_id' => $workspace->id,
                'workspace_name' => $workspace->name,
                'action_url' => '/workspaces',
            ],
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\WorkspaceApprovedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'type' => 'workspace.approved',
                'workspace_id' => $workspace->id,
                'workspace_name' => $workspace->name,
                'action_url' => '/workspaces',
            ],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/notifications?unread_only=1')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_notifications_support_pagination(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'approval_status' => 'approved',
        ]);

        for ($i = 0; $i < 3; $i++) {
            DatabaseNotification::query()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\WorkspaceApprovedNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => [
                    'type' => 'workspace.approved',
                    'workspace_id' => $workspace->id,
                    'workspace_name' => $workspace->name,
                    'action_url' => '/workspaces',
                ],
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/me/notifications?per_page=2');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 3);

        $this->assertLessThanOrEqual(2, count($response->json('data.data')));
    }

    public function test_marking_already_read_notification_is_idempotent(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'approval_status' => 'approved',
        ]);

        $notificationId = (string) Str::uuid();

        DatabaseNotification::query()->create([
            'id' => $notificationId,
            'type' => 'App\\Notifications\\WorkspaceApprovedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'type' => 'workspace.approved',
                'workspace_id' => $workspace->id,
                'workspace_name' => $workspace->name,
                'action_url' => '/workspaces',
            ],
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/me/notifications/{$notificationId}/read")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_unread_count_returns_zero_when_no_notifications(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/notifications/unread-count')
            ->assertStatus(200)
            ->assertJsonPath('data.count', 0);
    }
}
