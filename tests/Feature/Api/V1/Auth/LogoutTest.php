<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_logout_all_devices(): void
    {
        $user = User::factory()->create();

        // Create multiple tokens
        $user->createToken('token1');
        $user->createToken('token2');
        $user->createToken('token3');

        $this->assertCount(3, $user->tokens);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/logout-all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out from all devices successfully.',
            ]);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_unauthenticated_user_cannot_logout_all(): void
    {
        $response = $this->postJson('/api/v1/logout-all');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_inactive_user_is_blocked_by_middleware(): void
    {
        $user = User::factory()->inactive()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account has been deactivated.',
            ]);
    }
}
