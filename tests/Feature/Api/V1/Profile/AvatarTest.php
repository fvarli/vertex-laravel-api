<?php

namespace Tests\Feature\Api\V1\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private string $avatarEndpoint = '/api/v1/me/avatar';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_upload_avatar(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->postJson($this->avatarEndpoint, [
            'avatar' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Avatar uploaded successfully.',
            ]);

        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertStringStartsWith('avatars/', $user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_upload_replaces_existing_avatar(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $oldFile = UploadedFile::fake()->image('old.jpg', 200, 200);
        $this->postJson($this->avatarEndpoint, ['avatar' => $oldFile]);

        $user->refresh();
        $oldPath = $user->avatar;

        $newFile = UploadedFile::fake()->image('new.jpg', 200, 200);
        $this->postJson($this->avatarEndpoint, ['avatar' => $newFile]);

        $user->refresh();
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->avatar);
        $this->assertNotNull($user->avatar);
        $this->assertStringStartsWith('avatars/', $user->avatar);
        $this->assertNotEquals($oldPath, $user->avatar);
    }

    public function test_user_can_delete_avatar(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $this->postJson($this->avatarEndpoint, ['avatar' => $file]);

        $user->refresh();
        $avatarPath = $user->avatar;

        $response = $this->deleteJson($this->avatarEndpoint);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Avatar deleted successfully.',
            ]);

        $user->refresh();
        $this->assertNull($user->avatar);
        Storage::disk('public')->assertMissing($avatarPath);
    }

    public function test_upload_fails_with_invalid_file_type(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson($this->avatarEndpoint, [
            'avatar' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_upload_fails_with_oversized_file(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('large.jpg')->size(3000);

        $response = $this->postJson($this->avatarEndpoint, [
            'avatar' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_upload_fails_without_file(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson($this->avatarEndpoint);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_upload_fails_with_excessive_dimensions(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('huge.jpg', 5000, 5000)->size(1024);

        $response = $this->postJson($this->avatarEndpoint, [
            'avatar' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }
}
