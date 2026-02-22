<?php

namespace Tests\Unit\Policies;

use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\StudentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private User $trainer;

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
    }

    public function test_owner_admin_can_access(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $policy = app(StudentPolicy::class);

        $this->assertTrue($policy->view($this->owner, $student));
        $this->assertTrue($policy->update($this->owner, $student));
        $this->assertTrue($policy->setStatus($this->owner, $student));
    }

    public function test_trainer_can_access_own_resource(): void
    {
        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $policy = app(StudentPolicy::class);

        $this->assertTrue($policy->view($this->trainer, $student));
        $this->assertTrue($policy->update($this->trainer, $student));
        $this->assertTrue($policy->setStatus($this->trainer, $student));
    }

    public function test_trainer_cannot_access_other_trainer_resource(): void
    {
        $otherTrainer = User::factory()->trainer()->create();
        $this->workspace->users()->attach($otherTrainer->id, ['role' => 'trainer', 'is_active' => true]);
        $otherTrainer->update(['active_workspace_id' => $this->workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $otherTrainer->id,
        ]);

        $policy = app(StudentPolicy::class);

        $this->assertFalse($policy->view($this->trainer, $student));
        $this->assertFalse($policy->update($this->trainer, $student));
        $this->assertFalse($policy->setStatus($this->trainer, $student));
    }

    public function test_user_from_different_workspace_cannot_access(): void
    {
        $otherWorkspace = Workspace::factory()->create(['owner_user_id' => $this->owner->id]);

        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
        ]);

        $this->trainer->update(['active_workspace_id' => $otherWorkspace->id]);

        $policy = app(StudentPolicy::class);

        $this->assertFalse($policy->view($this->trainer, $student));
        $this->assertFalse($policy->update($this->trainer, $student));
        $this->assertFalse($policy->setStatus($this->trainer, $student));
    }
}
