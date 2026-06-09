<?php

namespace App\Repositories;

use App\DTO\CreateBookingData;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Collection;

class BookingRepository
{
    /**
     * @return Collection<int, Booking>
     */
    public function forUser(int $userId): Collection
    {
        return Booking::query()
            ->with('room')
            ->where('user_id', $userId)
            ->orderByDesc('starts_at')
            ->get();
    }

    /**
     * Half-open overlap [start, end): adjacent bookings do not conflict.
     */
    public function hasActiveOverlap(int $roomId, string $startsAt, string $endsAt): bool
    {
        return Booking::query()
            ->where('room_id', $roomId)
            ->whereIn('status', BookingStatus::activeValues())
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }

    public function create(CreateBookingData $data): Booking
    {
        return Booking::create([
            'room_id' => $data->roomId,
            'user_id' => $data->userId,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'participants_count' => $data->participantsCount,
            'status' => BookingStatus::Confirmed,
        ]);
    }

    public function markCancelled(Booking $booking): Booking
    {
        $booking->update(['status' => BookingStatus::Cancelled]);

        return $booking;
    }
}
