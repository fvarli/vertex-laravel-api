<?php

namespace Tests\Feature\Api\V1\Notification;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_notifications_and_counts_and_mark_them_as_read(): void
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
            'read_at' => null,
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

        $this->getJson('/api/v1/me/notifications')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);

        $notificationId = (string) $user->notifications()->latest('created_at')->value('id');

        $this->getJson('/api/v1/me/notifications/unread-count')
            ->assertStatus(200)
            ->assertJsonPath('data.count', 2);

        $this->patchJson("/api/v1/me/notifications/{$notificationId}/read")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/me/notifications/unread-count')
            ->assertStatus(200)
            ->assertJsonPath('data.count', 1);

        $this->patchJson('/api/v1/me/notifications/read-all')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/me/notifications/unread-count')
            ->assertStatus(200)
            ->assertJsonPath('data.count', 0);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $other->id,
            'approval_status' => 'approved',
        ]);

        DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\WorkspaceApprovedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $other->id,
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
        $notificationId = (string) $other->notifications()->latest('created_at')->value('id');

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/me/notifications/{$notificationId}/read")
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
