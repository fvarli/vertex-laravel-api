<?php

namespace Tests\Feature\Api\V1\Validation;

use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgramValidationTest extends TestCase
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

    public function test_title_is_required(): void
    {
        $response = $this->postJson("/api/v1/students/{$this->student->id}/programs", [
            'week_start_date' => '2026-06-08',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_title_min_length(): void
    {
        $response = $this->postJson("/api/v1/students/{$this->student->id}/programs", [
            'title' => 'AB',
            'week_start_date' => '2026-06-08',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_title_max_length(): void
    {
        $response = $this->postJson("/api/v1/students/{$this->student->id}/programs", [
            'title' => str_repeat('a', 151),
            'week_start_date' => '2026-06-08',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_week_start_date_is_required(): void
    {
        $response = $this->postJson("/api/v1/students/{$this->student->id}/programs", [
            'title' => 'Full Body',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['week_start_date']);
    }

    public function test_invalid_week_start_date_format(): void
    {
        $response = $this->postJson("/api/v1/students/{$this->student->id}/programs", [
            'title' => 'Full Body',
            'week_start_date' => 'not-a-date',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['week_start_date']);
    }

    public function test_invalid_status_value(): void
    {
        $response = $this->postJson("/api/v1/students/{$this->student->id}/programs", [
            'title' => 'Full Body',
            'week_start_date' => '2026-06-08',
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_item_day_of_week_boundary(): void
    {
        $response = $this->postJson("/api/v1/students/{$this->student->id}/programs", [
            'title' => 'Full Body',
            'week_start_date' => '2026-06-08',
            'items' => [
                ['day_of_week' => 0, 'order_no' => 1, 'exercise' => 'Squat'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.day_of_week']);
    }

    public function test_item_day_of_week_over_7(): void
    {
        $response = $this->postJson("/api/v1/students/{$this->student->id}/programs", [
            'title' => 'Full Body',
            'week_start_date' => '2026-06-08',
            'items' => [
                ['day_of_week' => 8, 'order_no' => 1, 'exercise' => 'Squat'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.day_of_week']);
    }

    public function test_duplicate_day_order_in_items(): void
    {
        $response = $this->postJson("/api/v1/students/{$this->student->id}/programs", [
            'title' => 'Full Body',
            'week_start_date' => '2026-06-08',
            'items' => [
                ['day_of_week' => 1, 'order_no' => 1, 'exercise' => 'Squat'],
                ['day_of_week' => 1, 'order_no' => 1, 'exercise' => 'Bench'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.order_no']);
    }

    public function test_goal_max_length(): void
    {
        $response = $this->postJson("/api/v1/students/{$this->student->id}/programs", [
            'title' => 'Full Body',
            'week_start_date' => '2026-06-08',
            'goal' => str_repeat('x', 2001),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['goal']);
    }

    public function test_item_exercise_min_length(): void
    {
        $response = $this->postJson("/api/v1/students/{$this->student->id}/programs", [
            'title' => 'Full Body',
            'week_start_date' => '2026-06-08',
            'items' => [
                ['day_of_week' => 1, 'order_no' => 1, 'exercise' => 'A'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.exercise']);
    }
}
