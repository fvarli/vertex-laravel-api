<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsurePlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    private EnsurePlatformAdmin $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new EnsurePlatformAdmin;
    }

    public function test_allows_platform_admin(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $admin);

        $called = false;
        $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response('ok');
        });

        $this->assertTrue($called);
    }

    public function test_blocks_workspace_user(): void
    {
        $user = User::factory()->create(['system_role' => 'workspace_user']);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_blocks_null_user(): void
    {
        $request = Request::create('/test');
        $request->setUserResolver(fn () => null);

        $response = $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }
}
