<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\CancelBookingAction;
use App\Actions\Booking\CreateBookingAction;
use App\Actions\Booking\ListUserBookingsAction;
use App\DTO\CreateBookingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Booking\StoreBookingRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class BookingController extends Controller
{
    #[OA\Get(
        path: '/api/bookings',
        tags: ['Bookings'],
        summary: 'Lista rezerwacji zalogowanego użytkownika',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Booking')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Brak autoryzacji', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ]
    )]
    public function index(Request $request, ListUserBookingsAction $action): AnonymousResourceCollection
    {
        return BookingResource::collection($action->execute($request->user()->id));
    }

    #[OA\Post(
        path: '/api/bookings',
        tags: ['Bookings'],
        summary: 'Utworzenie rezerwacji',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['room_id', 'starts_at', 'ends_at', 'participants_count'],
                properties: [
                    new OA\Property(property: 'room_id', type: 'integer', example: 4),
                    new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', example: '2026-06-19T12:00:00+00:00'),
                    new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', example: '2026-06-19T14:00:00+00:00'),
                    new OA\Property(property: 'participants_count', type: 'integer', minimum: 1, example: 5),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Utworzono',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Booking'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Brak autoryzacji', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 422, description: 'Błąd walidacji', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(StoreBookingRequest $request, CreateBookingAction $action): JsonResponse
    {
        $booking = $action->execute(new CreateBookingData(
            roomId: $request->integer('room_id'),
            userId: $request->user()->id,
            startsAt: $request->string('starts_at')->toString(),
            endsAt: $request->string('ends_at')->toString(),
            participantsCount: $request->integer('participants_count'),
        ));

        return BookingResource::make($booking->load('room'))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Patch(
        path: '/api/bookings/{booking}/cancel',
        tags: ['Bookings'],
        summary: 'Anulowanie rezerwacji',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'booking', in: 'path', required: true, description: 'ID rezerwacji', schema: new OA\Schema(type: 'integer', example: 12)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Booking'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Brak autoryzacji', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 403, description: 'Brak uprawnień do anulowania', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ]
    )]
    public function cancel(Request $request, Booking $booking, CancelBookingAction $action): BookingResource
    {
        $this->authorize('cancel', $booking);

        return BookingResource::make($action->execute($booking)->load('room'));
    }
}
