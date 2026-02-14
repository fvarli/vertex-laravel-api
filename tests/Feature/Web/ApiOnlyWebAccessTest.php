<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class ApiOnlyWebAccessTest extends TestCase
{
    public function test_web_root_returns_api_only_message(): void
    {
        $response = $this->get('/');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'This is an API-only application. No web access allowed.',
            ]);
    }

    public function test_unknown_web_path_returns_api_only_message(): void
    {
        $response = $this->get('/dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'This is an API-only application. No web access allowed.',
            ]);
    }
}
