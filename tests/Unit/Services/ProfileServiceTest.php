<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProfileService;
    }

    public function test_update_profile_updates_fields(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $updated = $this->service->updateProfile($user, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updated->name);
    }

    public function test_change_password_updates_password(): void
    {
        $user = User::factory()->create();

        $this->service->changePassword($user, 'newpassword123');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_change_password_revokes_other_tokens_keeps_current(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current');
        $user->createToken('other_device');

        $user->withAccessToken($currentToken->accessToken);
        $this->actingAs($user);

        $this->service->changePassword($user, 'newpassword123');

        $this->assertEquals(1, $user->tokens()->count());
        $this->assertNotNull($user->tokens()->find($currentToken->accessToken->id));
    }

    public function test_update_avatar_stores_file_and_updates_user(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar' => null]);
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $updated = $this->service->updateAvatar($user, $file);

        $this->assertNotNull($updated->avatar);
        Storage::disk('public')->assertExists($updated->avatar);
    }

    public function test_update_avatar_deletes_old_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar' => null]);
        $file1 = UploadedFile::fake()->image('avatar1.jpg', 200, 200);
        $updated = $this->service->updateAvatar($user, $file1);
        $oldPath = $updated->avatar;

        $file2 = UploadedFile::fake()->image('avatar2.jpg', 200, 200);
        $this->service->updateAvatar($updated, $file2);

        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_delete_avatar_removes_file_and_clears_field(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar' => null]);
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $updated = $this->service->updateAvatar($user, $file);
        $path = $updated->avatar;

        $this->service->deleteAvatar($updated);

        $this->assertNull($updated->fresh()->avatar);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_delete_avatar_noop_when_no_avatar(): void
    {
        $user = User::factory()->create(['avatar' => null]);

        $this->service->deleteAvatar($user);

        $this->assertNull($user->fresh()->avatar);
    }

    public function test_delete_account_soft_deletes_user(): void
    {
        $user = User::factory()->create();
        $user->createToken('auth_token');

        $this->service->deleteAccount($user);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertEquals(0, $user->tokens()->count());
    }
}
