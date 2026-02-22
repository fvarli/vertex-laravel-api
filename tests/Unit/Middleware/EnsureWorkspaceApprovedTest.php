<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureWorkspaceApproved;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureWorkspaceApprovedTest extends TestCase
{
    use RefreshDatabase;

    private EnsureWorkspaceApproved $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new EnsureWorkspaceApproved;
    }

    public function test_allows_approved_workspace(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $owner->id,
            'approval_status' => 'approved',
        ]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $owner);
        $request->attributes->set('workspace_id', $workspace->id);

        $called = false;
        $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response('ok');
        });

        $this->assertTrue($called);
    }

    public function test_blocks_pending_workspace(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $owner->id,
            'approval_status' => 'pending',
            'approved_at' => null,
        ]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $owner);
        $request->attributes->set('workspace_id', $workspace->id);

        $response = $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('pending', $data['data']['approval_status']);
    }

    public function test_blocks_rejected_workspace(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $owner->id,
            'approval_status' => 'rejected',
            'approved_at' => null,
        ]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $owner);
        $request->attributes->set('workspace_id', $workspace->id);

        $response = $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_platform_admin_bypasses_check(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => User::factory()->create()->id,
            'approval_status' => 'pending',
        ]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $admin);
        $request->attributes->set('workspace_id', $workspace->id);

        $called = false;
        $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response('ok');
        });

        $this->assertTrue($called);
    }
}
