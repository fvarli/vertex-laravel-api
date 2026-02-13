<?php

namespace Tests\Feature\RateLimiting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvatarUploadRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_upload_is_rate_limited_after_10_attempts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/me/avatar');
        }

        $response = $this->postJson('/api/v1/me/avatar');

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);
    }
}
