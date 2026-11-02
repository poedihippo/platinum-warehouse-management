<?php

namespace App\Http\Controllers\Api\Loyalty\Auth;

use App\Http\Controllers\Api\BaseController;
use App\Interfaces\Services\Loyalty\LoyaltyUser\LoyaltyAuthServiceInterface;
use Illuminate\Http\Request;

class LogoutController extends BaseController
{
    public function __construct(private LoyaltyAuthServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * POST /api/loyalty/auth/logout
     *
     * Revokes the token used for this request.
     */
    public function __invoke(Request $request)
    {
        $this->service->logout($request->user());

        return response()->json(['message' => 'Berhasil keluar.']);
    }
}
