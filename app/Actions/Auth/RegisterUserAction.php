<?php

namespace App\Actions\Auth;

use App\DTO\RegisterUserData;
use App\Models\User;

class RegisterUserAction
{
    /**
     * @return array{user: User, token: string}
     */
    public function execute(RegisterUserData $data): array
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password, // hashed by the model's 'hashed' cast
        ]);

        return [
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }
}
