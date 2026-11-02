<?php

namespace App\Interfaces\Services\Loyalty\LoyaltyUser;

use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Contracts\Auth\Authenticatable;

interface LoyaltyAuthServiceInterface
{
    public function register(mixed $data): LoyaltyUser;

    public function verifyEmail(mixed $id, mixed $hash): string;

    public function login(mixed $email, mixed $password): array;

    public function logout(Authenticatable $user): void;

    public function requestPasswordReset(mixed $email): void;

    public function resetPassword(mixed $credentials): bool;
}
