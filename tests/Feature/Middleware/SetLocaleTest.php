<?php

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_english(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $this->assertEquals('en', app()->getLocale());
    }

    public function test_turkish_locale_with_accept_language_header(): void
    {
        $response = $this->getJson('/api/v1/nonexistent', [
            'Accept-Language' => 'tr',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Kaynak bulunamadı.',
            ]);
    }

    public function test_english_messages_with_en_locale(): void
    {
        $response = $this->getJson('/api/v1/nonexistent', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found.',
            ]);
    }

    public function test_unsupported_locale_falls_back_to_english(): void
    {
        $response = $this->getJson('/api/v1/nonexistent', [
            'Accept-Language' => 'fr',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found.',
            ]);
    }
}
