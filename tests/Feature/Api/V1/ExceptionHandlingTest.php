<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExceptionHandlingTest extends TestCase
{
    public function test_http_exception_message_is_not_exposed_to_client(): void
    {
        if (! Route::has('tests.http-exception')) {
            Route::middleware('api')
                ->get('/api/v1/test-http-exception', fn () => abort(418, 'Secret internal detail'))
                ->name('tests.http-exception');
        }

        $response = $this->getJson('/api/v1/test-http-exception');

        $response->assertStatus(418)
            ->assertJson([
                'success' => false,
                'message' => 'Error',
            ]);

        $this->assertStringNotContainsString('Secret internal detail', json_encode($response->json()));
    }
}
