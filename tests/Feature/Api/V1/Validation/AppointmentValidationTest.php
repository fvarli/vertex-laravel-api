<?php

namespace Tests\Feature\Api\V1\Validation;

use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->ownerAdmin()->create();
        $this->workspace = Workspace::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->workspace->users()->attach($this->owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $this->owner->update(['active_workspace_id' => $this->workspace->id]);

        $this->student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->owner->id,
        ]);

        Sanctum::actingAs($this->owner);
    }

    public function test_student_id_is_required(): void
    {
        $response = $this->postJson('/api/v1/appointments', [
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id']);
    }

    public function test_starts_at_is_required(): void
    {
        $response = $this->postJson('/api/v1/appointments', [
            'student_id' => $this->student->id,
            'ends_at' => '2026-06-10 11:00:00',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);
    }

    public function test_ends_at_must_be_after_starts_at(): void
    {
        $response = $this->postJson('/api/v1/appointments', [
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 11:00:00',
            'ends_at' => '2026-06-10 10:00:00',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at']);
    }

    public function test_invalid_date_format_rejected(): void
    {
        $response = $this->postJson('/api/v1/appointments', [
            'student_id' => $this->student->id,
            'starts_at' => 'not-a-date',
            'ends_at' => '2026-06-10 11:00:00',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);
    }

    public function test_location_max_length(): void
    {
        $response = $this->postJson('/api/v1/appointments', [
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'location' => str_repeat('x', 161),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['location']);
    }

    public function test_notes_max_length(): void
    {
        $response = $this->postJson('/api/v1/appointments', [
            'student_id' => $this->student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'notes' => str_repeat('x', 2001),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['notes']);
    }

    public function test_student_id_must_be_integer(): void
    {
        $response = $this->postJson('/api/v1/appointments', [
            'student_id' => 'abc',
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id']);
    }

    public function test_nonexistent_student_id_rejected(): void
    {
        $response = $this->postJson('/api/v1/appointments', [
            'student_id' => 99999,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id']);
    }
}
