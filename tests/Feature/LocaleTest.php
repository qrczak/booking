<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_use_polish_when_accept_language_is_pl(): void
    {
        $response = $this->postJson('/api/register', [], ['Accept-Language' => 'pl']);

        $response->assertStatus(422);
        $this->assertStringContainsString('wymagane', $response->json('errors.name.0'));
    }

    public function test_validation_messages_use_english_when_accept_language_is_en(): void
    {
        $response = $this->postJson('/api/register', [], ['Accept-Language' => 'en']);

        $response->assertStatus(422);
        $this->assertStringContainsString('required', $response->json('errors.name.0'));
    }
}
