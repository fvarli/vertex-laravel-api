<?php

namespace Tests\Feature\Api\V1\EmailVerification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VerifyEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_verify_email_with_valid_hash(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $hash = sha1($user->getEmailForVerification());

        $response = $this->postJson("/api/v1/email/verify/{$user->id}/{$hash}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email verified successfully.',
            ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verify_fails_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/email/verify/{$user->id}/invalid-hash");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid verification link.',
            ]);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_already_verified_user_gets_appropriate_message(): void
    {
        $user = User::factory()->create(); // already verified by default
        Sanctum::actingAs($user);

        $hash = sha1($user->getEmailForVerification());

        $response = $this->postJson("/api/v1/email/verify/{$user->id}/{$hash}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email is already verified.',
            ]);
    }
}
