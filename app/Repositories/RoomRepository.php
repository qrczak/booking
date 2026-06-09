<?php

namespace App\Repositories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

class RoomRepository
{
    /**
     * @return Collection<int, Room>
     */
    public function all(): Collection
    {
        return Room::query()->orderBy('name')->get();
    }

    public function find(int $id): ?Room
    {
        return Room::find($id);
    }
}
