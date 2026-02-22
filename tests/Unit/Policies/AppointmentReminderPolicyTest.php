<?php

namespace Tests\Unit\Policies;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\AppointmentReminderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentReminderPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private User $trainer;

    private Student $student;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->ownerAdmin()->create();
        $this->trainer = User::factory()->trainer()->create();
        $this->workspace = Workspace::factory()->create(['owner_user_id' => $this->owner->id]);

        $this->workspace->users()->attach($this->owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $this->workspace->users()->attach($this->trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $this->owner->update(['active_workspace_id' => $this->workspace->id]);
        $this->trainer->update(['active_workspace_id' => $this->workspace->id]);

        $this->student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $this->appointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $this->student->id,
        ]);
    }

    public function test_owner_admin_can_access(): void
    {
        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $this->appointment->id,
        ]);

        $policy = app(AppointmentReminderPolicy::class);

        $this->assertTrue($policy->view($this->owner, $reminder));
        $this->assertTrue($policy->update($this->owner, $reminder));
    }

    public function test_trainer_can_access_own_resource(): void
    {
        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $this->appointment->id,
        ]);

        $policy = app(AppointmentReminderPolicy::class);

        $this->assertTrue($policy->view($this->trainer, $reminder));
        $this->assertTrue($policy->update($this->trainer, $reminder));
    }

    public function test_trainer_cannot_access_other_trainer_resource(): void
    {
        $otherTrainer = User::factory()->trainer()->create();
        $this->workspace->users()->attach($otherTrainer->id, ['role' => 'trainer', 'is_active' => true]);
        $otherTrainer->update(['active_workspace_id' => $this->workspace->id]);

        $otherAppointment = Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $otherTrainer->id,
            'student_id' => $this->student->id,
        ]);

        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $otherAppointment->id,
        ]);

        $policy = app(AppointmentReminderPolicy::class);

        $this->assertFalse($policy->view($this->trainer, $reminder));
        $this->assertFalse($policy->update($this->trainer, $reminder));
    }

    public function test_user_from_different_workspace_cannot_access(): void
    {
        $otherWorkspace = Workspace::factory()->create(['owner_user_id' => $this->owner->id]);

        $reminder = AppointmentReminder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'appointment_id' => $this->appointment->id,
        ]);

        $this->trainer->update(['active_workspace_id' => $otherWorkspace->id]);

        $policy = app(AppointmentReminderPolicy::class);

        $this->assertFalse($policy->view($this->trainer, $reminder));
        $this->assertFalse($policy->update($this->trainer, $reminder));
    }
}
