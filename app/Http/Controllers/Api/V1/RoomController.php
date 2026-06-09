<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RoomResource;
use App\Repositories\RoomRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class RoomController extends Controller
{
    #[OA\Get(
        path: '/api/rooms',
        tags: ['Rooms'],
        summary: 'Lista pokoi',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Room')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Brak autoryzacji', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ]
    )]
    public function index(RoomRepository $rooms): AnonymousResourceCollection
    {
        return RoomResource::collection($rooms->all());
    }
}
