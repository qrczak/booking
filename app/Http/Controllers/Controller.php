<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Booking API',
    version: '1.0.0',
    description: 'API rezerwacji pokoi.'
)]
#[OA\Server(
    url: 'https://booking.test',
    description: 'Local (Herd)'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    description: 'Token Sanctum: Authorization: Bearer <token>'
)]
#[OA\Tag(name: 'Auth', description: 'Rejestracja, logowanie, wylogowanie')]
#[OA\Tag(name: 'Rooms', description: 'Pokoje')]
#[OA\Tag(name: 'Bookings', description: 'Rezerwacje')]
#[OA\Tag(name: 'Translations', description: 'Tłumaczenia interfejsu')]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(property: 'errors', type: 'object', description: 'Mapa: pole => lista komunikatów błędów'),
    ]
)]
#[OA\Schema(
    schema: 'ErrorMessage',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
    ]
)]
#[OA\Schema(
    schema: 'AuthResponse',
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'token', type: 'string', example: '1|abcDEF123...'),
    ]
)]
abstract class Controller
{
    use AuthorizesRequests;
}
