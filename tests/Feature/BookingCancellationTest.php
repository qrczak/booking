<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_cancel_own_booking(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);
        $booking = Booking::factory()->for($user)->for($room)->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_user_cannot_cancel_another_users_booking(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        $booking = Booking::factory()->for(User::factory())->for($room)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/bookings/{$booking->id}/cancel")->assertForbidden();
    }

    public function test_guest_cannot_cancel_booking(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        $booking = Booking::factory()->for(User::factory())->for($room)->create();

        $this->patchJson("/api/bookings/{$booking->id}/cancel")->assertUnauthorized();
    }
}
