<?php

namespace Tests\Feature\RateLimiting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VerifyEmailRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_email_is_rate_limited_after_6_attempts(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $url = URL::temporarySignedRoute('v1.verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => 'invalid-hash',
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson($url);
        }

        $response = $this->postJson($url);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);
    }
}
