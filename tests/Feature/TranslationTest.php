<?php

namespace Tests\Feature;

use Tests\TestCase;

class TranslationTest extends TestCase
{
    public function test_translations_endpoint_returns_messages_for_requested_locale(): void
    {
        $this->getJson('/api/translations?locale=pl')
            ->assertOk()
            ->assertJsonPath('locale', 'pl')
            ->assertJsonPath('messages.app.title', 'Rezerwacja pokoi')
            ->assertJsonStructure(['locale', 'version', 'messages' => ['app', 'auth', 'rooms', 'bookings']]);
    }

    public function test_translations_endpoint_serves_english(): void
    {
        $this->getJson('/api/translations?locale=en')
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('messages.app.title', 'Room Booking');
    }

    public function test_translations_endpoint_falls_back_for_unknown_locale(): void
    {
        $this->getJson('/api/translations?locale=de')
            ->assertOk()
            ->assertJsonPath('locale', config('app.fallback_locale'));
    }

    public function test_translations_endpoint_is_public(): void
    {
        $this->getJson('/api/translations?locale=pl')->assertOk();
    }

    public function test_version_is_stable_across_requests(): void
    {
        $first = $this->getJson('/api/translations?locale=pl')->json('version');
        $second = $this->getJson('/api/translations?locale=pl')->json('version');

        $this->assertNotEmpty($first);
        $this->assertSame($first, $second);
    }
}
