<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Repositories\BookingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlap_detection_ignores_adjacent_and_cancelled_bookings(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        Booking::factory()->for($room)->create([
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
        ]);
        Booking::factory()->cancelled()->for($room)->create([
            'starts_at' => '2026-07-01 13:00:00',
            'ends_at' => '2026-07-01 14:00:00',
        ]);

        $repository = app(BookingRepository::class);

        $this->assertTrue($repository->hasActiveOverlap($room->id, '2026-07-01 10:30:00', '2026-07-01 11:30:00'));
        $this->assertFalse($repository->hasActiveOverlap($room->id, '2026-07-01 11:00:00', '2026-07-01 12:00:00'));
        $this->assertFalse($repository->hasActiveOverlap($room->id, '2026-07-01 13:00:00', '2026-07-01 14:00:00'));
    }
}
