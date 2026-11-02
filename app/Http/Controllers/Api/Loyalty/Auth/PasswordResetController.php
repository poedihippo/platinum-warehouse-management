<?php

namespace App\Http\Controllers\Api\Loyalty\Auth;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Loyalty\Auth\PasswordResetConfirmRequest;
use App\Http\Requests\Api\Loyalty\Auth\PasswordResetRequest;
use App\Interfaces\Services\Loyalty\LoyaltyUser\LoyaltyAuthServiceInterface;

class PasswordResetController extends BaseController
{
    public function __construct(private LoyaltyAuthServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * POST /api/loyalty/auth/password-reset/request
     *
     * Always answers with the same message so the endpoint cannot be used to
     * find out which emails are registered.
     */
    public function request(PasswordResetRequest $request)
    {
        $this->service->requestPasswordReset($request->email);

        return response()->json([
            'message' => 'Jika email terdaftar, tautan reset kata sandi telah dikirim.',
        ]);
    }

    /**
     * POST /api/loyalty/auth/password-reset/confirm
     */
    public function confirm(PasswordResetConfirmRequest $request)
    {
        if ($this->service->resetPassword($request->only('email', 'password', 'password_confirmation', 'token'))) {
            return response()->json(['message' => 'Kata sandi berhasil direset.']);
        }

        return response()->json([
            'message' => 'Token reset tidak valid atau kedaluwarsa.',
        ], 422);
    }
}
