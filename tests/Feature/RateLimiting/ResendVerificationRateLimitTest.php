<?php

namespace Tests\Feature\RateLimiting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResendVerificationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_verification_is_rate_limited_after_3_attempts(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/email/resend');
        }

        $response = $this->postJson('/api/v1/email/resend');

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);
    }
}
