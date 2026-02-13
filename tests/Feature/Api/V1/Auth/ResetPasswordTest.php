<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/v1/reset-password';

    public function test_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson($this->endpoint, [
            'email' => 'john@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password has been reset successfully.',
            ]);

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson($this->endpoint, [
            'email' => 'john@example.com',
            'token' => 'invalid-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ]);
    }

    public function test_reset_fails_with_expired_token(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);
        $token = Password::createToken($user);

        // Manually expire the token by updating created_at
        $this->travel(61)->minutes();

        $response = $this->postJson($this->endpoint, [
            'email' => 'john@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_validation_fails_without_required_fields(): void
    {
        $response = $this->postJson($this->endpoint, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'token', 'password']);
    }

    public function test_validation_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson($this->endpoint, [
            'email' => 'invalid-email',
            'token' => 'token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_validation_fails_with_weak_password(): void
    {
        $response = $this->postJson($this->endpoint, [
            'email' => 'john@example.com',
            'token' => 'token',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_validation_fails_when_password_confirmation_does_not_match(): void
    {
        $response = $this->postJson($this->endpoint, [
            'email' => 'john@example.com',
            'token' => 'token',
            'password' => 'newpassword123',
            'password_confirmation' => 'mismatch-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
