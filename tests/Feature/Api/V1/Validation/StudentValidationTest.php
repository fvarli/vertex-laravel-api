<?php

namespace Tests\Feature\Api\V1\Validation;

use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->ownerAdmin()->create();
        $this->workspace = Workspace::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->workspace->users()->attach($this->owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $this->owner->update(['active_workspace_id' => $this->workspace->id]);

        Sanctum::actingAs($this->owner);
    }

    public function test_full_name_is_required(): void
    {
        $response = $this->postJson('/api/v1/students', [
            'phone' => '+905551234567',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name']);
    }

    public function test_full_name_min_length(): void
    {
        $response = $this->postJson('/api/v1/students', [
            'full_name' => 'A',
            'phone' => '+905551234567',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name']);
    }

    public function test_full_name_max_length(): void
    {
        $response = $this->postJson('/api/v1/students', [
            'full_name' => str_repeat('a', 121),
            'phone' => '+905551234567',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name']);
    }

    public function test_phone_is_required(): void
    {
        $response = $this->postJson('/api/v1/students', [
            'full_name' => 'John Doe',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_phone_min_length(): void
    {
        $response = $this->postJson('/api/v1/students', [
            'full_name' => 'John Doe',
            'phone' => '1234567',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_phone_max_length(): void
    {
        $response = $this->postJson('/api/v1/students', [
            'full_name' => 'John Doe',
            'phone' => str_repeat('1', 33),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_duplicate_phone_in_workspace_rejected(): void
    {
        Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->owner->id,
            'phone' => '+905551234567',
        ]);

        $response = $this->postJson('/api/v1/students', [
            'full_name' => 'Another Student',
            'phone' => '+905551234567',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_notes_max_length(): void
    {
        $response = $this->postJson('/api/v1/students', [
            'full_name' => 'John Doe',
            'phone' => '+905551234567',
            'notes' => str_repeat('x', 2001),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['notes']);
    }
}
