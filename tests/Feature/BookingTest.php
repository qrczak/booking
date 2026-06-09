<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_booking_for_available_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);
        Sanctum::actingAs($user);

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
            'participants_count' => 4,
        ])->assertCreated()->assertJsonPath('data.status.value', 'confirmed');

        $this->assertDatabaseHas('bookings', [
            'room_id' => $room->id,
            'user_id' => $user->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_booking_fails_when_time_overlaps_active_booking(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        Booking::factory()->for($room)->create([
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 10:30:00',
            'ends_at' => '2026-07-01 11:30:00',
            'participants_count' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('starts_at');
    }

    public function test_adjacent_booking_is_allowed(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        Booking::factory()->for($room)->create([
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 11:00:00',
            'ends_at' => '2026-07-01 12:00:00',
            'participants_count' => 2,
        ])->assertCreated();
    }

    public function test_cancelled_booking_does_not_block_slot(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        Booking::factory()->cancelled()->for($room)->create([
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
            'participants_count' => 2,
        ])->assertCreated();
    }

    public function test_booking_fails_when_participants_exceed_capacity(): void
    {
        $room = Room::factory()->create(['capacity' => 3]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
            'participants_count' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors('participants_count');
    }

    public function test_ends_at_must_be_after_starts_at(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 11:00:00',
            'ends_at' => '2026-07-01 10:00:00',
            'participants_count' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('ends_at');
    }

    public function test_user_only_sees_their_own_bookings(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);
        Booking::factory()->for($user)->for($room)->create();
        Booking::factory()->for($other)->for($room)->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_guest_cannot_list_bookings(): void
    {
        $this->getJson('/api/bookings')->assertUnauthorized();
    }

    public function test_guest_cannot_create_booking(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
            'participants_count' => 2,
        ])->assertUnauthorized();
    }

    public function test_validation_uses_polish_attribute_names_and_translated_summary(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/bookings', [], ['Accept-Language' => 'pl']);

        $response->assertStatus(422);
        $this->assertStringContainsString('data rozpoczęcia rezerwacji', $response->json('errors.starts_at.0'));
        $this->assertStringContainsString('więcej błędów', $response->json('message'));
    }
}
