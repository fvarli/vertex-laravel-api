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
        $this->service = new PushNotificationServiceTestable;
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

    public function test_send_calls_fcm_v1_api_when_enabled(): void
    {
        config(['fcm.enabled' => true, 'fcm.project_id' => 'test-project']);

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/test-project/messages/12345'], 200),
        ]);

        $result = $this->service->send('device-token', 'Title', 'Body', ['key' => 'value']);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fcm.googleapis.com/v1/projects/test-project/messages:send'
                && $request->hasHeader('Authorization', 'Bearer fake-access-token')
                && $request['message']['token'] === 'device-token'
                && $request['message']['notification']['title'] === 'Title'
                && $request['message']['notification']['body'] === 'Body'
                && $request['message']['data']['key'] === 'value';
        });
    }

    public function test_send_returns_false_when_api_fails(): void
    {
        config(['fcm.enabled' => true, 'fcm.project_id' => 'test-project']);

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['error' => ['message' => 'Invalid registration']], 400),
        ]);

        $result = $this->service->send('bad-token', 'Title', 'Body');

        $this->assertFalse($result);
    }

    public function test_send_returns_false_when_no_project_id(): void
    {
        config(['fcm.enabled' => true, 'fcm.project_id' => null]);

        $result = $this->service->send('token', 'Title', 'Body');

        $this->assertFalse($result);
    }

    public function test_send_returns_false_when_credentials_missing(): void
    {
        config(['fcm.enabled' => true, 'fcm.project_id' => 'test-project']);

        $service = new PushNotificationServiceNoCredentials;

        $result = $service->send('token', 'Title', 'Body');

        $this->assertFalse($result);
    }

    public function test_send_casts_data_values_to_strings(): void
    {
        config(['fcm.enabled' => true, 'fcm.project_id' => 'test-project']);

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/test-project/messages/123'], 200),
        ]);

        $this->service->send('device-token', 'Title', 'Body', ['id' => 42, 'active' => true]);

        Http::assertSent(function ($request) {
            return $request['message']['data']['id'] === '42';
        });
    }
}

/**
 * Testable subclass that bypasses real OAuth2 token fetching.
 */
class PushNotificationServiceTestable extends PushNotificationService
{
    protected function getAccessToken(): ?string
    {
        return 'fake-access-token';
    }
}

/**
 * Subclass that simulates missing credentials.
 */
class PushNotificationServiceNoCredentials extends PushNotificationService
{
    protected function getAccessToken(): ?string
    {
        return null;
    }
}
