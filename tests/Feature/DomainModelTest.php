<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_casts_status_to_enum_and_belongs_to_room(): void
    {
        $room = Room::factory()->create(['capacity' => 5]);
        $booking = Booking::factory()->for($room)->create();

        $this->assertInstanceOf(BookingStatus::class, $booking->status);
        $this->assertSame($room->id, $booking->room->id);
        $this->assertSame(5, $room->capacity);
    }
}
