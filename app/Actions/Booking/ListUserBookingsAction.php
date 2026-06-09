<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Repositories\BookingRepository;
use Illuminate\Database\Eloquent\Collection;

class ListUserBookingsAction
{
    public function __construct(private readonly BookingRepository $bookings) {}

    /**
     * @return Collection<int, Booking>
     */
    public function execute(int $userId): Collection
    {
        return $this->bookings->forUser($userId);
    }
}
