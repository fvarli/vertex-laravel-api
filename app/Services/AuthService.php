<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::query()->create([
            'name'     => $data['name'],
            'surname'  => $data['surname'] ?? null,
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => $data['password'],
            'is_active' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    /**
     * @throws AuthenticationException
     */
    public function login(array $credentials): array
    {
        if (! Auth::attempt($credentials)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            throw new AuthenticationException('Your account has been deactivated.');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }
}
