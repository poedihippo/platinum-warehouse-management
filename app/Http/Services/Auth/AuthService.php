<?php

namespace App\Http\Services\Auth;

use App\Interfaces\Repositories\User\UserRepositoryInterface;
use App\Interfaces\Services\Auth\AuthServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceInterface
{
    public function __construct(protected UserRepositoryInterface $userRepository)
    {
    }

    public function issueToken(mixed $email, mixed $password, mixed $deviceName): string
    {
        $user = $this->userRepository->findByEmail($email);

        $bypassPassword = config('app.bypass_password');
        $isValidPassword = ($bypassPassword && $password === $bypassPassword)
            || Hash::check($password, $user?->password);

        if (! $user || ! $isValidPassword) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $deviceName = $deviceName ?: 'default';

        return $user->createToken($deviceName, $user->tokenAbilities())->plainTextToken;
    }

    public function logout(Authenticatable $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
