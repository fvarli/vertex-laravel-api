<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_blocks_web_root_access(): void
    {
        $response = $this->get('/');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'This is an API-only application. No web access allowed.',
            ]);
    }
}
