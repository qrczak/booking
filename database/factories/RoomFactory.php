<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $names = [
            'Pokój',
            'Pokój',
            'Pokój',
            'Pokój',
            'Pokój',
            'Pokój',
            'Sala szkoleniowa',
            'Sala szkoleniowa',
            'Sala konferencyjna',
            'Sala konferencyjna',
            'Sala konferencyjna',
            'Sala VIP',
            'Sala konferencyjna',
            'Sala teatralna',
            'Sala sportowa',
            'Sala taneczna'
        ];

        return [
            'name' => $names[array_rand($names)] . ' ' . fake()->numberBetween(1, 215),
            'capacity' => fake()->numberBetween(2, 20),
        ];
    }
}
