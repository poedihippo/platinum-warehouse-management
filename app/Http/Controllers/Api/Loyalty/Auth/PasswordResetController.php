<?php

namespace App\Http\Controllers\Api\Loyalty\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Loyalty\LoyaltyPasswordResetConfirmRequest;
use App\Http\Requests\Api\Loyalty\LoyaltyPasswordResetRequest;
use App\Mail\Loyalty\PasswordResetMail;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    private const BROKER = 'loyalty_users';

    /**
     * Request a reset token. Always returns the same generic success
     * response so the endpoint cannot be used to probe which emails
     * are registered.
     */
    public function request(LoyaltyPasswordResetRequest $request)
    {
        $user = LoyaltyUser::where('email', $request->email)->first();

        if ($user) {
            $token = Password::broker(self::BROKER)->createToken($user);
            Mail::to($user->email)->send(new PasswordResetMail($user->email, $token));
        }

        return response()->json([
            'message' => 'Jika email terdaftar, tautan reset kata sandi telah dikirim.',
        ]);
    }

    /**
     * Confirm the token and set the new password via Laravel's password
     * broker configured for the loyalty_users provider.
     */
    public function confirm(LoyaltyPasswordResetConfirmRequest $request)
    {
        $status = Password::broker(self::BROKER)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (LoyaltyUser $user, string $password) {
                // Model mutator hashes the password on assignment.
                $user->forceFill(['password' => $password])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Kata sandi berhasil direset.']);
        }

        return response()->json([
            'message' => 'Token reset tidak valid atau kedaluwarsa.',
        ], 422);
    }
}
