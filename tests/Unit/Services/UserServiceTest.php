<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserService;
    }

    public function test_list_returns_paginated_users(): void
    {
        User::factory()->count(3)->create();

        $result = $this->service->list(['per_page' => 2]);

        $this->assertCount(2, $result->items());
        $this->assertEquals(3, $result->total());
    }

    public function test_list_filters_by_search(): void
    {
        User::factory()->create(['name' => 'John', 'surname' => 'Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane', 'surname' => 'Smith', 'email' => 'jane@example.com']);

        $result = $this->service->list(['search' => 'John']);

        $this->assertCount(1, $result->items());
        $this->assertEquals('John', $result->items()[0]->name);
    }
}
