<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\LogoutUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\DTO\RegisterUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/register',
        tags: ['Auth'],
        summary: 'Rejestracja nowego użytkownika',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Jan Kowalski'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jan@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'haslo1234'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'haslo1234'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Utworzono', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse')),
            new OA\Response(response: 422, description: 'Błąd walidacji', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $result = $action->execute(new RegisterUserData(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
        ));

        return response()->json([
            'user' => UserResource::make($result['user']),
            'token' => $result['token'],
        ], 201);
    }

    #[OA\Post(
        path: '/api/login',
        tags: ['Auth'],
        summary: 'Logowanie',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jan@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'haslo1234'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse')),
            new OA\Response(response: 422, description: 'Błąd walidacji', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'user' => UserResource::make($result['user']),
            'token' => $result['token'],
        ]);
    }

    #[OA\Post(
        path: '/api/logout',
        tags: ['Auth'],
        summary: 'Wylogowanie (unieważnia bieżący token)',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 401, description: 'Brak autoryzacji', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ]
    )]
    public function logout(Request $request, LogoutUserAction $action): JsonResponse
    {
        $action->execute($request->user());

        return response()->json(['message' => __('auth.logged_out')]);
    }
}
