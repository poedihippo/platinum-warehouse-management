<?php

namespace App\Interfaces\Services\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

interface AuthServiceInterface
{
    public function issueToken(mixed $email, mixed $password, mixed $deviceName): string;

    public function logout(Authenticatable $user): void;
}
