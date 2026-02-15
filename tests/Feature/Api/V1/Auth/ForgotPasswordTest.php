<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/v1/forgot-password';

    public function test_sends_reset_link_for_valid_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson($this->endpoint, [
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'If your email is registered, you will receive a password reset link.',
            ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_returns_200_for_nonexistent_email(): void
    {
        Notification::fake();

        $response = $this->postJson($this->endpoint, [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'If your email is registered, you will receive a password reset link.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_validation_fails_without_email(): void
    {
        $response = $this->postJson($this->endpoint, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
