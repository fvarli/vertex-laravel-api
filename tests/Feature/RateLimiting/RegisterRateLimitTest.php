<?php

namespace Tests\Feature\RateLimiting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_is_rate_limited_after_3_attempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/register', [
                'name' => 'User',
                'email' => "user{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        $response = $this->postJson('/api/v1/register', [
            'name' => 'User',
            'email' => 'user99@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(429);
    }
}
