<?php

namespace App\Http\Services\Loyalty\LoyaltyUser;

use App\Events\Loyalty\LoyaltyUserRegistered;
use App\Exceptions\ApiResponseException;
use App\Interfaces\Repositories\Loyalty\LoyaltyUser\LoyaltyUserRepositoryInterface;
use App\Interfaces\Services\Loyalty\LoyaltyUser\LoyaltyAuthServiceInterface;
use App\Mail\Loyalty\PasswordResetMail;
use App\Models\Loyalty\LoyaltyUser;
use App\Support\Loyalty\LoyaltySignedUrl;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class LoyaltyAuthService implements LoyaltyAuthServiceInterface
{
    private const BROKER = 'loyalty_users';

    public function __construct(protected LoyaltyUserRepositoryInterface $repository)
    {
    }

    public function register(mixed $data): LoyaltyUser
    {
        $user = $this->repository->create($data);

        event(new LoyaltyUserRegistered($user));

        return $user;
    }

    public function verifyEmail(mixed $id, mixed $hash): string
    {
        $user = $this->repository->findById($id);

        if (! $user || ! hash_equals(sha1($user->email), (string) $hash)) {
            throw new ApiResponseException('Tautan verifikasi tidak valid.', 403);
        }

        if ($user->hasVerifiedEmail()) {
            return 'Email sudah diverifikasi sebelumnya.';
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return 'Email berhasil diverifikasi.';
    }

    public function login(mixed $email, mixed $password): array
    {
        $user = $this->repository->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new ApiResponseException('Email atau kata sandi salah.', 422);
        }

        if (! $user->is_active) {
            throw new ApiResponseException('Akun dinonaktifkan.', 403);
        }

        $token = $user->createToken('loyalty', ['loyalty'])->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function logout(Authenticatable $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function requestPasswordReset(mixed $email): void
    {
        $user = $this->repository->findByEmail($email);

        if ($user) {
            $token = Password::broker(self::BROKER)->createToken($user);
            $resetUrl = LoyaltySignedUrl::passwordReset($token, $user->email);

            Mail::to($user->email)->send(new PasswordResetMail($user->email, $resetUrl));
        }
    }

    public function resetPassword(mixed $credentials): bool
    {
        $status = Password::broker(self::BROKER)->reset(
            $credentials,
            function (LoyaltyUser $user, string $password) {
                $user->forceFill(['password' => $password])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET;
    }
}
