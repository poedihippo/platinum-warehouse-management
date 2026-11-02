<?php

namespace App\Http\Controllers\Api\Loyalty\Auth;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Loyalty\Auth\LoginRequest;
use App\Http\Resources\Loyalty\LoyaltyUserResource;
use App\Interfaces\Services\Loyalty\LoyaltyUser\LoyaltyAuthServiceInterface;

class LoginController extends BaseController
{
    public function __construct(private LoyaltyAuthServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * POST /api/loyalty/auth/login
     *
     * Issues a Sanctum token scoped to the loyalty ability only.
     */
    public function __invoke(LoginRequest $request)
    {
        $result = $this->service->login($request->email, $request->password);

        return response()->json([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'data' => new LoyaltyUserResource($result['user']),
        ]);
    }
}
