<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_rooms(): void
    {
        Room::factory()->count(3)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/rooms')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'capacity']]]);
    }

    public function test_guest_cannot_list_rooms(): void
    {
        $this->getJson('/api/rooms')->assertUnauthorized();
    }
}
