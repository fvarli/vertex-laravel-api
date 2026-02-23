<?php

namespace Tests\Unit\Services;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\DeviceTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeviceTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeviceTokenService;
    }

    public function test_list_for_user_returns_tokens(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->count(2)->create(['user_id' => $user->id]);

        $otherUser = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $otherUser->id]);

        $result = $this->service->listForUser($user->id);

        $this->assertCount(2, $result);
    }

    public function test_register_creates_new_token(): void
    {
        $user = User::factory()->create();

        $token = $this->service->register($user->id, [
            'token' => 'fcm-token-123',
            'platform' => 'web',
            'device_name' => 'Chrome',
        ]);

        $this->assertEquals('fcm-token-123', $token->token);
        $this->assertEquals('web', $token->platform);
        $this->assertTrue($token->is_active);
    }

    public function test_register_updates_existing_token(): void
    {
        $user = User::factory()->create();

        $this->service->register($user->id, [
            'token' => 'fcm-token-123',
            'platform' => 'web',
        ]);

        $this->service->register($user->id, [
            'token' => 'fcm-token-123',
            'platform' => 'android',
        ]);

        $this->assertEquals(1, DeviceToken::where('user_id', $user->id)->count());
        $this->assertEquals('android', DeviceToken::where('user_id', $user->id)->first()->platform);
    }

    public function test_delete_removes_token(): void
    {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->service->delete($token);

        $this->assertNull(DeviceToken::find($token->id));
    }
}
