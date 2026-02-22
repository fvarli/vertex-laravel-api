<?php

namespace Tests\Feature\Api\V1\Notification;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_device_token(): void
    {
        $user = User::factory()->verifiedActive()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/devices', [
            'platform' => 'ios',
            'token' => 'fcm-token-abc123',
            'device_name' => 'iPhone 15',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.platform', 'ios')
            ->assertJsonPath('data.token', 'fcm-token-abc123')
            ->assertJsonPath('data.device_name', 'iPhone 15')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-abc123',
        ]);
    }

    public function test_duplicate_token_updates_existing(): void
    {
        $user = User::factory()->verifiedActive()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/devices', [
            'platform' => 'android',
            'token' => 'dup-token-xyz',
        ]);

        $response = $this->postJson('/api/v1/devices', [
            'platform' => 'android',
            'token' => 'dup-token-xyz',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(1, DeviceToken::where('token', 'dup-token-xyz')->count());
        $this->assertEquals('Pixel 8', DeviceToken::where('token', 'dup-token-xyz')->first()->device_name);
    }

    public function test_user_can_list_devices(): void
    {
        $user = User::factory()->verifiedActive()->create();
        DeviceToken::factory()->count(2)->create(['user_id' => $user->id]);
        DeviceToken::factory()->create(); // Another user's token
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/devices');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_delete_own_device(): void
    {
        $user = User::factory()->verifiedActive()->create();
        $token = DeviceToken::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/devices/{$token->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('device_tokens', ['id' => $token->id]);
    }

    public function test_user_cannot_delete_others_device(): void
    {
        $user = User::factory()->verifiedActive()->create();
        $otherToken = DeviceToken::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/devices/{$otherToken->id}");

        $response->assertStatus(403);
    }

    public function test_validation_requires_platform_and_token(): void
    {
        $user = User::factory()->verifiedActive()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/devices', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform', 'token']);
    }

    public function test_validation_rejects_invalid_platform(): void
    {
        $user = User::factory()->verifiedActive()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/devices', [
            'platform' => 'windows',
            'token' => 'some-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_unauthenticated_cannot_manage_devices(): void
    {
        $response = $this->getJson('/api/v1/devices');
        $response->assertStatus(401);
    }
}
