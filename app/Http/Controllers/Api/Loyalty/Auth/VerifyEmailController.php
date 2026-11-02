<?php

namespace App\Http\Controllers\Api\Loyalty\Auth;

use App\Http\Controllers\Api\BaseController;
use App\Interfaces\Services\Loyalty\LoyaltyUser\LoyaltyAuthServiceInterface;
use Illuminate\Http\Request;

class VerifyEmailController extends BaseController
{
    public function __construct(private LoyaltyAuthServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/loyalty/auth/verify-email/{id}/{hash}
     *
     * The signature is checked by the loyalty.signed middleware; the hash
     * ties the link to the email it was sent to.
     */
    public function __invoke(Request $request, string $id, string $hash)
    {
        return response()->json(['message' => $this->service->verifyEmail($id, $hash)]);
    }
}
