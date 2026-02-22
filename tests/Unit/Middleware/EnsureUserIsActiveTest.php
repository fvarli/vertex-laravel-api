<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    use RefreshDatabase;

    private EnsureUserIsActive $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new EnsureUserIsActive;
    }

    public function test_allows_active_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->createToken('auth_token');
        $user->withAccessToken($user->tokens()->first());

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $called = false;
        $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response('ok');
        });

        $this->assertTrue($called);
    }

    public function test_blocks_inactive_user_with_403(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $token = $user->createToken('auth_token');
        $user->withAccessToken($token->accessToken);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_inactive_user_token_is_deleted(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $token = $user->createToken('auth_token');
        $user->withAccessToken($token->accessToken);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(0, $user->tokens()->count());
    }
}
