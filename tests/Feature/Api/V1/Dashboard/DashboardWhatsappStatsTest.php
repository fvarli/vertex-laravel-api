<?php

namespace Tests\Feature\Api\V1\Dashboard;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardWhatsappStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_includes_whatsapp_stats(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainer = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'status' => Student::STATUS_ACTIVE,
        ]);

        // 2 planned appointments today - 1 sent, 1 not_sent
        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $student->id,
            'starts_at' => now()->startOfDay()->addHours(9),
            'ends_at' => now()->startOfDay()->addHours(10),
            'status' => Appointment::STATUS_PLANNED,
            'whatsapp_status' => Appointment::WHATSAPP_STATUS_SENT,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $student->id,
            'starts_at' => now()->startOfDay()->addHours(11),
            'ends_at' => now()->startOfDay()->addHours(12),
            'status' => Appointment::STATUS_PLANNED,
            'whatsapp_status' => Appointment::WHATSAPP_STATUS_NOT_SENT,
        ]);

        // Cancelled appointment should not count
        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainer->id,
            'student_id' => $student->id,
            'starts_at' => now()->startOfDay()->addHours(14),
            'ends_at' => now()->startOfDay()->addHours(15),
            'status' => Appointment::STATUS_CANCELLED,
            'whatsapp_status' => Appointment::WHATSAPP_STATUS_NOT_SENT,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            ->assertJsonPath('data.whatsapp.today_total', 2)
            ->assertJsonPath('data.whatsapp.today_sent', 1)
            ->assertJsonPath('data.whatsapp.today_not_sent', 1)
            ->assertJsonPath('data.whatsapp.send_rate', 50);
    }

    public function test_whatsapp_stats_empty_when_no_appointments(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            ->assertJsonPath('data.whatsapp.today_total', 0)
            ->assertJsonPath('data.whatsapp.today_sent', 0)
            ->assertJsonPath('data.whatsapp.today_not_sent', 0)
            ->assertJsonPath('data.whatsapp.send_rate', 0);
    }

    public function test_trainer_sees_only_own_whatsapp_stats(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $trainerA = User::factory()->trainer()->create();
        $trainerB = User::factory()->trainer()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $workspace->users()->attach($trainerA->id, ['role' => 'trainer', 'is_active' => true]);
        $workspace->users()->attach($trainerB->id, ['role' => 'trainer', 'is_active' => true]);
        $trainerA->update(['active_workspace_id' => $workspace->id]);

        $studentA = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerA->id,
        ]);

        $studentB = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerB->id,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerA->id,
            'student_id' => $studentA->id,
            'starts_at' => now()->startOfDay()->addHours(9),
            'ends_at' => now()->startOfDay()->addHours(10),
            'status' => Appointment::STATUS_PLANNED,
            'whatsapp_status' => Appointment::WHATSAPP_STATUS_SENT,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $trainerB->id,
            'student_id' => $studentB->id,
            'starts_at' => now()->startOfDay()->addHours(9),
            'ends_at' => now()->startOfDay()->addHours(10),
            'status' => Appointment::STATUS_PLANNED,
            'whatsapp_status' => Appointment::WHATSAPP_STATUS_SENT,
        ]);

        Sanctum::actingAs($trainerA);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            ->assertJsonPath('data.whatsapp.today_total', 1)
            ->assertJsonPath('data.whatsapp.today_sent', 1);
    }
}
