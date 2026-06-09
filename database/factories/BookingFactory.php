<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+2 weeks');
        $endsAt = (clone $startsAt)->modify('+1 hour');

        return [
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'participants_count' => 2,
            'status' => BookingStatus::Confirmed,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Cancelled,
        ]);
    }
}
