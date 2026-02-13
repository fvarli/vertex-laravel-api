<?php

namespace Tests\Feature\Api\V1\EmailVerification;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResendVerificationTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/v1/email/resend';

    public function test_can_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson($this->endpoint);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Verification link sent.',
            ]);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_already_verified_user_gets_appropriate_message(): void
    {
        Notification::fake();

        $user = User::factory()->create(); // already verified
        Sanctum::actingAs($user);

        $response = $this->postJson($this->endpoint);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email is already verified.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_unauthenticated_user_cannot_resend(): void
    {
        $response = $this->postJson($this->endpoint);

        $response->assertStatus(401);
    }
}
