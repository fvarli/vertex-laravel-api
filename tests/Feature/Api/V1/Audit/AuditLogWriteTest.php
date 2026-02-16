<?php

namespace Tests\Feature\Api\V1\Audit;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_program_and_appointment_mutations_write_audit_logs(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
        ]);

        $program = Program::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $student->id,
            'trainer_user_id' => $owner->id,
            'status' => Program::STATUS_DRAFT,
        ]);

        $appointment = Appointment::factory()->create([
            'workspace_id' => $workspace->id,
            'student_id' => $student->id,
            'trainer_user_id' => $owner->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
            'status' => Appointment::STATUS_PLANNED,
        ]);

        Sanctum::actingAs($owner);

        $this->withHeaders(['X-Request-Id' => 'audit-test-req-001'])
            ->patchJson("/api/v1/students/{$student->id}/status", ['status' => Student::STATUS_PASSIVE])
            ->assertOk();

        $this->withHeaders(['X-Request-Id' => 'audit-test-req-002'])
            ->patchJson("/api/v1/programs/{$program->id}/status", ['status' => Program::STATUS_ACTIVE])
            ->assertOk();

        $this->withHeaders(['X-Request-Id' => 'audit-test-req-003'])
            ->patchJson("/api/v1/appointments/{$appointment->id}/status", ['status' => Appointment::STATUS_DONE])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'student.status_updated',
            'actor_user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'request_id' => 'audit-test-req-001',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'program.status_updated',
            'actor_user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'request_id' => 'audit-test-req-002',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'appointment.status_updated',
            'actor_user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'request_id' => 'audit-test-req-003',
        ]);

        $studentLog = AuditLog::query()->where('event', 'student.status_updated')->firstOrFail();
        $this->assertArrayHasKey('before', $studentLog->changes);
        $this->assertArrayHasKey('after', $studentLog->changes);
        $this->assertSame(Student::STATUS_PASSIVE, $studentLog->changes['after']['status'] ?? null);
        $this->assertArrayNotHasKey('notes', $studentLog->changes['after']);
    }
}
