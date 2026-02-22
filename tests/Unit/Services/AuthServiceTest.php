<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AuthService;
    }

    public function test_register_creates_user_and_returns_token(): void
    {
        $result = $this->service->register([
            'name' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('john@example.com', $result['user']->email);
        $this->assertTrue($result['user']->is_active);
        $this->assertEquals('workspace_user', $result['user']->system_role);
    }

    public function test_register_creates_auth_token(): void
    {
        $result = $this->service->register([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $this->assertNotEmpty($result['token']);
        $this->assertEquals(1, $result['user']->tokens()->count());
    }

    public function test_login_returns_user_and_token(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $result = $this->service->login([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('test@example.com', $result['user']->email);
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(AuthenticationException::class);

        $this->service->login([
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);
    }

    public function test_login_fails_for_deactivated_user(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'password123',
            'is_active' => false,
        ]);

        $this->expectException(AuthenticationException::class);

        $this->service->login([
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);
    }

    public function test_logout_deletes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token');

        $this->actingAs($user);
        $user->withAccessToken($token->accessToken);

        $this->service->logout($user);

        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_logout_all_deletes_all_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('token_1');
        $user->createToken('token_2');
        $user->createToken('token_3');

        $this->assertEquals(3, $user->tokens()->count());

        $this->service->logoutAll($user);

        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_refresh_token_deletes_current_and_creates_new(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token');
        $user->withAccessToken($token->accessToken);

        $this->actingAs($user);

        $newToken = $this->service->refreshToken($user);

        $this->assertNotEmpty($newToken);
        $this->assertEquals(1, $user->tokens()->count());
    }

    public function test_send_reset_link_delegates_to_password_broker(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $result = $this->service->sendResetLink('reset@example.com');

        $this->assertEquals(Password::RESET_LINK_SENT, $result);
    }

    public function test_reset_password_changes_password_and_deletes_tokens(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $user->createToken('auth_token');

        $token = Password::createToken($user);

        $result = $this->service->resetPassword([
            'email' => 'reset@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token' => $token,
        ]);

        $this->assertEquals(Password::PASSWORD_RESET, $result);
        $this->assertEquals(0, $user->fresh()->tokens()->count());
    }
}
