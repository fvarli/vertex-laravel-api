<?php

namespace Tests\Feature\Api\V1\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private string $showEndpoint = '/api/v1/me';

    private string $updateEndpoint = '/api/v1/me';

    private string $passwordEndpoint = '/api/v1/me/password';

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->showEndpoint);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'surname', 'email', 'phone', 'avatar', 'is_active', 'created_at', 'updated_at'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $response = $this->getJson($this->showEndpoint);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson($this->updateEndpoint, [
            'name' => 'Updated Name',
            'surname' => 'Updated Surname',
            'phone' => '+905551234567',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Name',
                    'surname' => 'Updated Surname',
                    'phone' => '+905551234567',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'surname' => 'Updated Surname',
        ]);
    }

    public function test_user_can_update_partial_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'surname' => 'Original Surname',
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson($this->updateEndpoint, [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Name',
                    'surname' => 'Original Surname',
                ],
            ]);
    }

    public function test_profile_update_fails_with_invalid_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson($this->updateEndpoint, [
            'name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson($this->passwordEndpoint, [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully.',
            ]);
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson($this->passwordEndpoint, [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_change_password_fails_with_weak_new_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson($this->passwordEndpoint, [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_inactive_user_cannot_access_profile(): void
    {
        $user = User::factory()->inactive()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->showEndpoint);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account has been deactivated.',
            ]);
    }
}
