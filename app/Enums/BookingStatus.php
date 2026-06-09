<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /**
     * Statuses that occupy a room's time slot.
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed];
    }

    /**
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return array_map(static fn(self $status): string => $status->value, self::active());
    }

    public function label(): string
    {
        return __('bookings.' . $this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Confirmed => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-red-100 text-red-800',
        };
    }
}
