<?php

namespace Tests\Feature\Api\V1\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserListTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/v1/users';

    public function test_authenticated_user_can_list_users(): void
    {
        User::factory()->count(5)->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->endpoint);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'request_id',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'surname', 'email', 'phone', 'avatar', 'is_active', 'created_at', 'updated_at'],
                    ],
                    'links',
                    'meta',
                ],
                'meta',
                'links',
            ]);
    }

    public function test_pagination_respects_per_page_parameter(): void
    {
        User::factory()->count(10)->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->endpoint.'?per_page=3');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data['data']);
        $this->assertEquals(3, $data['meta']['per_page']);
    }

    public function test_pagination_default_per_page_is_15(): void
    {
        User::factory()->count(20)->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->endpoint);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(15, $data['meta']['per_page']);
    }

    public function test_pagination_per_page_max_is_50(): void
    {
        User::factory()->count(5)->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->endpoint.'?per_page=100');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(50, $data['meta']['per_page']);
    }

    public function test_pagination_per_page_min_is_1(): void
    {
        User::factory()->count(5)->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->endpoint.'?per_page=0');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(1, $data['meta']['per_page']);
    }

    public function test_pagination_page_navigation(): void
    {
        User::factory()->count(10)->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->endpoint.'?per_page=5&page=2');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(2, $data['meta']['current_page']);
        $this->assertNotEmpty($data['data']);
    }

    public function test_search_and_sort_contract_filters_user_list(): void
    {
        $userA = User::factory()->create([
            'name' => 'Aaron',
            'email' => 'aaron.search-test@vertex.local',
        ]);
        $userB = User::factory()->create([
            'name' => 'Zelda',
            'email' => 'zelda.search-test@vertex.local',
        ]);
        $authUser = User::factory()->create();
        Sanctum::actingAs($authUser);

        $response = $this->getJson($this->endpoint.'?search=zelda.search-test@vertex.local&sort=name&direction=asc&per_page=15');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.id', $userB->id);

        $this->assertNotEquals($userA->id, $response->json('data.data.0.id'));
    }

    public function test_unauthenticated_user_cannot_list_users(): void
    {
        $response = $this->getJson($this->endpoint);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_unverified_user_cannot_list_users(): void
    {
        User::factory()->count(3)->create();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->endpoint);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }
}
