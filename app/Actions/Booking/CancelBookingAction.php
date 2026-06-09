<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Repositories\BookingRepository;

class CancelBookingAction
{
    public function __construct(private readonly BookingRepository $bookings) {}

    public function execute(Booking $booking): Booking
    {
        return $this->bookings->markCancelled($booking);
    }
}
