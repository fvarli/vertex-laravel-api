<?php

namespace Tests\Feature\Api\V1\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/v1/me';

    public function test_user_can_delete_account(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson($this->endpoint, [
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ]);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_delete_account_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson($this->endpoint, [
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_account_fails_without_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson($this->endpoint);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_delete_account_revokes_all_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('token-1');
        $user->createToken('token-2');
        Sanctum::actingAs($user);

        $this->deleteJson($this->endpoint, [
            'password' => 'password',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_soft_deleted_user_still_exists_in_database(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson($this->endpoint, [
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
