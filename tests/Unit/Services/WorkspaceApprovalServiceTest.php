<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceApprovedNotification;
use App\Notifications\WorkspaceRejectedNotification;
use App\Services\WorkspaceApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkspaceApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkspaceApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkspaceApprovalService::class);
    }

    public function test_approve_sets_status_and_metadata(): void
    {
        Notification::fake();

        $owner = User::factory()->ownerAdmin()->create();
        $approver = User::factory()->platformAdmin()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $owner->id,
            'approval_status' => 'pending',
            'approved_at' => null,
        ]);

        $result = $this->service->approve($workspace, $approver, 'Looks good');

        $this->assertEquals('approved', $result->approval_status);
        $this->assertNotNull($result->approved_at);
        $this->assertEquals($approver->id, $result->approved_by_user_id);
        $this->assertEquals('Looks good', $result->approval_note);
    }

    public function test_approve_sends_approved_notification_to_owner(): void
    {
        Notification::fake();

        $owner = User::factory()->ownerAdmin()->create();
        $approver = User::factory()->platformAdmin()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $owner->id,
            'approval_status' => 'pending',
        ]);

        $this->service->approve($workspace, $approver);

        Notification::assertSentTo($owner, WorkspaceApprovedNotification::class);
    }

    public function test_reject_sets_status_and_metadata(): void
    {
        Notification::fake();

        $owner = User::factory()->ownerAdmin()->create();
        $approver = User::factory()->platformAdmin()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $owner->id,
            'approval_status' => 'pending',
        ]);

        $result = $this->service->reject($workspace, $approver, 'Missing info');

        $this->assertEquals('rejected', $result->approval_status);
        $this->assertNull($result->approved_at);
        $this->assertEquals($approver->id, $result->approved_by_user_id);
        $this->assertEquals('Missing info', $result->approval_note);
    }

    public function test_reject_sends_rejected_notification_to_owner(): void
    {
        Notification::fake();

        $owner = User::factory()->ownerAdmin()->create();
        $approver = User::factory()->platformAdmin()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $owner->id,
            'approval_status' => 'pending',
        ]);

        $this->service->reject($workspace, $approver, 'Not enough detail');

        Notification::assertSentTo($owner, WorkspaceRejectedNotification::class);
    }

    public function test_approve_with_null_note(): void
    {
        Notification::fake();

        $owner = User::factory()->ownerAdmin()->create();
        $approver = User::factory()->platformAdmin()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $owner->id,
            'approval_status' => 'pending',
        ]);

        $result = $this->service->approve($workspace, $approver);

        $this->assertEquals('approved', $result->approval_status);
        $this->assertNull($result->approval_note);
    }
}
