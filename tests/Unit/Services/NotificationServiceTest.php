<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService;
    }

    private function createNotification(User $user, ?string $readAt = null): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'test'],
            'read_at' => $readAt,
        ]);
    }

    public function test_list_returns_paginated_notifications(): void
    {
        $user = User::factory()->create();
        $this->createNotification($user);
        $this->createNotification($user);

        $result = $this->service->list($user, ['per_page' => 1]);

        $this->assertCount(1, $result->items());
        $this->assertEquals(2, $result->total());
    }

    public function test_list_filters_unread_only(): void
    {
        $user = User::factory()->create();
        $this->createNotification($user);
        $this->createNotification($user, now()->toDateTimeString());

        $result = $this->service->list($user, ['unread_only' => true]);

        $this->assertCount(1, $result->items());
    }

    public function test_unread_count_returns_correct_count(): void
    {
        $user = User::factory()->create();
        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, now()->toDateTimeString());

        $count = $this->service->unreadCount($user);

        $this->assertEquals(2, $count);
    }

    public function test_mark_read_sets_read_at(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $result = $this->service->markRead($notification);

        $this->assertNotNull($result->read_at);
    }

    public function test_mark_all_read_marks_all(): void
    {
        $user = User::factory()->create();
        $this->createNotification($user);
        $this->createNotification($user);

        $this->service->markAllRead($user);

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }
}
