<?php

namespace App\DTO;

class CreateBookingData
{
    public function __construct(
        public readonly int $roomId,
        public readonly int $userId,
        public readonly string $startsAt,
        public readonly string $endsAt,
        public readonly int $participantsCount,
    ) {}
}
