<?php

namespace Tests\Feature\Api\V1\Calendar;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_calendar_availability(): void
    {
        [$owner, $workspace, $student] = $this->seedContext();

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => '2026-06-03 09:00:00',
            'ends_at' => '2026-06-03 10:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => '2026-06-03 11:00:00',
            'ends_at' => '2026-06-03 12:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/calendar/availability?from=2026-06-01&to=2026-06-07');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.from', '2026-06-01')
            ->assertJsonPath('data.to', '2026-06-07')
            ->assertJsonCount(2, 'data.appointments')
            ->assertJsonCount(1, 'data.days')
            ->assertJsonPath('data.days.0.date', '2026-06-03')
            ->assertJsonCount(2, 'data.days.0.items');
    }

    public function test_calendar_availability_excludes_cancelled_appointments(): void
    {
        [$owner, $workspace, $student] = $this->seedContext();

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => '2026-06-04 09:00:00',
            'ends_at' => '2026-06-04 10:00:00',
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'student_id' => $student->id,
            'starts_at' => '2026-06-04 11:00:00',
            'ends_at' => '2026-06-04 12:00:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/calendar/availability?from=2026-06-01&to=2026-06-07');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.appointments')
            ->assertJsonCount(1, 'data.days')
            ->assertJsonPath('data.days.0.date', '2026-06-04')
            ->assertJsonCount(1, 'data.days.0.items');
    }

    public function test_calendar_requires_workspace_context(): void
    {
        $user = User::factory()->ownerAdmin()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/calendar/availability?from=2026-06-01&to=2026-06-07');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    private function seedContext(): array
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
        ]);

        return [$owner, $workspace, $student];
    }
}
