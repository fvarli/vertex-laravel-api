<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/v1/refresh-token';

    public function test_authenticated_user_can_refresh_token(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('auth_token')->plainTextToken;

        $this->assertCount(1, $user->tokens);

        $response = $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->postJson($this->endpoint);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Token refreshed successfully.',
            ])
            ->assertJsonStructure([
                'data' => ['token'],
            ]);

        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);

        // Old token deleted, new token created — still 1 token total
        $this->assertCount(1, $user->fresh()->tokens);

        // New token should work
        $this->withHeader('Authorization', "Bearer {$newToken}")
            ->getJson('/api/v1/me')
            ->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_refresh_token(): void
    {
        $response = $this->postJson($this->endpoint);

        $response->assertStatus(401);
    }

    public function test_inactive_user_cannot_refresh_token(): void
    {
        $user = User::factory()->inactive()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson($this->endpoint);

        $response->assertStatus(403);
    }
}
