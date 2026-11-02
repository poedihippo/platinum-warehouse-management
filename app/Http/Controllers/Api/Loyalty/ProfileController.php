<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Loyalty\Profile\UpdateRequest;
use App\Http\Resources\Loyalty\LoyaltyProfileResource;
use App\Interfaces\Services\Loyalty\LoyaltyUser\LoyaltyProfileServiceInterface;
use Illuminate\Http\Request;

class ProfileController extends BaseController
{
    public function __construct(private LoyaltyProfileServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/loyalty/profile
     */
    public function index(Request $request)
    {
        return new LoyaltyProfileResource($request->user());
    }

    /**
     * PATCH /api/loyalty/profile
     */
    public function update(UpdateRequest $request)
    {
        $user = $this->service->update($request->user(), $request->validated());

        return new LoyaltyProfileResource($user);
    }
}
