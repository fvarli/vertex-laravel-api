<?php

namespace Tests\Feature\RateLimiting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetPasswordRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_is_rate_limited_after_5_attempts(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/reset-password', [
                'email' => 'john@example.com',
                'token' => 'invalid-token',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);
        }

        $response = $this->postJson('/api/v1/reset-password', [
            'email' => 'john@example.com',
            'token' => 'invalid-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);
    }
}
