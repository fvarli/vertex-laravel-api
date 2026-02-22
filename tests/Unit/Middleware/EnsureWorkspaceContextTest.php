<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureWorkspaceContext;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureWorkspaceContextTest extends TestCase
{
    use RefreshDatabase;

    private EnsureWorkspaceContext $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = app(EnsureWorkspaceContext::class);
    }

    public function test_sets_workspace_id_and_role_on_request(): void
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $owner);

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals($workspace->id, $request->attributes->get('workspace_id'));
        $this->assertEquals('owner_admin', $request->attributes->get('workspace_role'));
    }

    public function test_throws_when_no_active_workspace(): void
    {
        $user = User::factory()->create(['active_workspace_id' => null]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $this->expectException(AuthorizationException::class);

        $this->middleware->handle($request, fn () => response('ok'));
    }

    public function test_throws_when_membership_inactive(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $user->id]);
        $workspace->users()->attach($user->id, ['role' => 'trainer', 'is_active' => false]);
        $user->update(['active_workspace_id' => $workspace->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $this->expectException(AuthorizationException::class);

        $this->middleware->handle($request, fn () => response('ok'));
    }

    public function test_passes_through_when_no_user(): void
    {
        $request = Request::create('/test');
        $request->setUserResolver(fn () => null);

        $called = false;
        $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response('ok');
        });

        $this->assertTrue($called);
    }
}
