<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Booking',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 12),
        new OA\Property(property: 'room_id', type: 'integer', example: 4),
        new OA\Property(property: 'room', ref: '#/components/schemas/Room', nullable: true),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', example: '2026-06-19T12:00:00+00:00'),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', example: '2026-06-19T14:00:00+00:00'),
        new OA\Property(property: 'participants_count', type: 'integer', example: 5),
        new OA\Property(property: 'status', type: 'object', properties: [
            new OA\Property(property: 'label', type: 'string', example: 'Potwierdzona'),
            new OA\Property(property: 'color', type: 'string', example: 'bg-green-100 text-green-800'),
            new OA\Property(property: 'value', type: 'string', enum: ['pending', 'confirmed', 'cancelled'], example: 'confirmed'),
        ]),
    ]
)]
class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'room' => RoomResource::make($this->whenLoaded('room')),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'participants_count' => $this->participants_count,
            'status' => [
                'label' => $this->status->label(),
                'color' => $this->status->color(),
                'value' => $this->status->value,
            ],
        ];
    }
}
