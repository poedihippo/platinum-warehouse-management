<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Loyalty\LoyaltyProfileUpdateRequest;
use App\Http\Resources\Loyalty\LoyaltyProfileResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * GET /api/loyalty/profile
     */
    public function index(Request $request)
    {
        return new LoyaltyProfileResource($request->user());
    }

    /**
     * PATCH /api/loyalty/profile
     *
     * Only phone/address are updatable here. Email is the login identifier
     * (unique, verified) and name comes from registration — neither is
     * accepted by LoyaltyProfileUpdateRequest, so they're silently ignored
     * even if sent.
     */
    public function update(LoyaltyProfileUpdateRequest $request)
    {
        $user = $request->user();
        $user->update($request->validated());

        return new LoyaltyProfileResource($user);
    }
}
