<?php

namespace App\Actions\Booking;

use App\DTO\CreateBookingData;
use App\Models\Booking;
use App\Repositories\BookingRepository;
use App\Repositories\RoomRepository;
use Illuminate\Validation\ValidationException;

class CreateBookingAction
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly RoomRepository $rooms,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(CreateBookingData $data): Booking
    {
        $room = $this->rooms->find($data->roomId);

        if ($room === null) {
            throw ValidationException::withMessages([
                'room_id' => [__('validation.exists', ['attribute' => 'room id'])],
            ]);
        }

        if ($data->participantsCount > $room->capacity) {
            throw ValidationException::withMessages([
                'participants_count' => [__('bookings.capacity_exceeded')],
            ]);
        }

        if ($this->bookings->hasActiveOverlap($data->roomId, $data->startsAt, $data->endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => [__('bookings.room_unavailable')],
            ]);
        }

        return $this->bookings->create($data);
    }
}
