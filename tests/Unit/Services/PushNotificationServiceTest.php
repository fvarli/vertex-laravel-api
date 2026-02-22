<?php

namespace Tests\Unit\Services;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private PushNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PushNotificationService;
    }

    public function test_send_to_user_returns_count_of_active_tokens(): void
    {
        config(['fcm.enabled' => false]);

        $user = User::factory()->verifiedActive()->create();
        DeviceToken::factory()->count(2)->create(['user_id' => $user->id, 'is_active' => true]);
        DeviceToken::factory()->create(['user_id' => $user->id, 'is_active' => false]);

        $sent = $this->service->sendToUser($user, 'Test', 'Body');

        $this->assertEquals(2, $sent);
    }

    public function test_send_to_user_with_no_tokens_returns_zero(): void
    {
        config(['fcm.enabled' => false]);

        $user = User::factory()->verifiedActive()->create();

        $sent = $this->service->sendToUser($user, 'Test', 'Body');

        $this->assertEquals(0, $sent);
    }

    public function test_send_skips_when_fcm_disabled(): void
    {
        config(['fcm.enabled' => false]);

        $result = $this->service->send('some-token', 'Title', 'Body');

        $this->assertTrue($result);
    }

    public function test_send_calls_fcm_api_when_enabled(): void
    {
        config(['fcm.enabled' => true, 'fcm.server_key' => 'test-key']);

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['success' => 1], 200),
        ]);

        $result = $this->service->send('device-token', 'Title', 'Body', ['key' => 'value']);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fcm.googleapis.com/fcm/send'
                && $request->hasHeader('Authorization', 'key=test-key')
                && $request['to'] === 'device-token'
                && $request['notification']['title'] === 'Title';
        });
    }

    public function test_send_returns_false_when_api_fails(): void
    {
        config(['fcm.enabled' => true, 'fcm.server_key' => 'test-key']);

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['error' => 'InvalidRegistration'], 400),
        ]);

        $result = $this->service->send('bad-token', 'Title', 'Body');

        $this->assertFalse($result);
    }

    public function test_send_returns_false_when_no_server_key(): void
    {
        config(['fcm.enabled' => true, 'fcm.server_key' => null]);

        $result = $this->service->send('token', 'Title', 'Body');

        $this->assertFalse($result);
    }
}
