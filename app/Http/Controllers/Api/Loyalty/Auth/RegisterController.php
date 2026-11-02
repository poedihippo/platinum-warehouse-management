<?php

namespace App\Http\Controllers\Api\Loyalty\Auth;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Loyalty\Auth\RegisterRequest;
use App\Http\Resources\Loyalty\LoyaltyUserResource;
use App\Interfaces\Services\Loyalty\LoyaltyUser\LoyaltyAuthServiceInterface;

class RegisterController extends BaseController
{
    public function __construct(private LoyaltyAuthServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * POST /api/loyalty/auth/register
     *
     * The verification email is sent by the LoyaltyUserRegistered listener.
     */
    public function __invoke(RegisterRequest $request)
    {
        $user = $this->service->register($request->only('name', 'email', 'password'));

        return response()->json([
            'message' => 'Registrasi berhasil. Silakan cek email Anda untuk verifikasi.',
            'data' => new LoyaltyUserResource($user),
        ], 201);
    }
}
