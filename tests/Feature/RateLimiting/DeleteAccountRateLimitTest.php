<?php

namespace Tests\Feature\RateLimiting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAccountRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_account_is_rate_limited_after_3_attempts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        for ($i = 0; $i < 3; $i++) {
            $this->deleteJson('/api/v1/me', [
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->deleteJson('/api/v1/me', [
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);
    }
}
