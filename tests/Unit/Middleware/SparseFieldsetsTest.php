<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SparseFieldsets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class SparseFieldsetsTest extends TestCase
{
    private SparseFieldsets $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SparseFieldsets;
    }

    public function test_filters_fields_on_single_resource(): void
    {
        $request = Request::create('/test?fields=id,name', 'GET');

        $response = $this->middleware->handle($request, function () {
            return new JsonResponse([
                'success' => true,
                'data' => ['id' => 1, 'name' => 'John', 'email' => 'john@test.com', 'phone' => '123'],
            ]);
        });

        $data = $response->getData(true);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $data['data']);
    }

    public function test_filters_fields_on_collection(): void
    {
        $request = Request::create('/test?fields=id,name', 'GET');

        $response = $this->middleware->handle($request, function () {
            return new JsonResponse([
                'success' => true,
                'data' => [
                    ['id' => 1, 'name' => 'John', 'email' => 'john@test.com'],
                    ['id' => 2, 'name' => 'Jane', 'email' => 'jane@test.com'],
                ],
            ]);
        });

        $data = $response->getData(true);
        $this->assertEquals([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ], $data['data']);
    }

    public function test_filters_fields_on_paginated_response(): void
    {
        $request = Request::create('/test?fields=id,name', 'GET');

        $response = $this->middleware->handle($request, function () {
            return new JsonResponse([
                'success' => true,
                'data' => [
                    'data' => [
                        ['id' => 1, 'name' => 'John', 'email' => 'john@test.com'],
                    ],
                    'meta' => ['current_page' => 1],
                ],
            ]);
        });

        $data = $response->getData(true);
        $this->assertEquals([['id' => 1, 'name' => 'John']], $data['data']['data']);
        $this->assertArrayHasKey('meta', $data['data']);
    }

    public function test_returns_full_response_when_no_fields_param(): void
    {
        $request = Request::create('/test', 'GET');
        $original = ['id' => 1, 'name' => 'John', 'email' => 'john@test.com'];

        $response = $this->middleware->handle($request, function () use ($original) {
            return new JsonResponse(['success' => true, 'data' => $original]);
        });

        $data = $response->getData(true);
        $this->assertEquals($original, $data['data']);
    }

    public function test_ignores_post_requests(): void
    {
        $request = Request::create('/test?fields=id', 'POST');

        $response = $this->middleware->handle($request, function () {
            return new JsonResponse([
                'success' => true,
                'data' => ['id' => 1, 'name' => 'John'],
            ]);
        });

        $data = $response->getData(true);
        $this->assertArrayHasKey('name', $data['data']);
    }
}
