<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $users = User::factory(8)->create();
        $users->first()->update(['email' => 'test@example.com']); // known login, password "password"

        $rooms = Room::factory(5)->create();

        $baseDay = CarbonImmutable::parse('next monday')->setTime(9, 0);

        foreach ($users->values() as $userIndex => $user) {
            foreach (range(0, 2) as $slot) {
                $room = $rooms->random();

                // Each user occupies its own 3-day window, each booking its own hour:
                // no two seeded bookings ever overlap.
                $start = $baseDay->addDays(($userIndex * 3) + $slot)->addHours($slot);

                Booking::factory()->create([
                    'user_id' => $user->id,
                    'room_id' => $room->id,
                    'starts_at' => $start,
                    'ends_at' => $start->addHour(),
                    'participants_count' => min(2, $room->capacity),
                    'status' => BookingStatus::Confirmed,
                ]);
            }
        }
    }
}
