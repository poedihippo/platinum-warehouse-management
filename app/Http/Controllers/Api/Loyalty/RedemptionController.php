<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Loyalty\Redemption\StoreRequest;
use App\Http\Resources\Loyalty\RedemptionResource;
use App\Interfaces\Services\Loyalty\Redemption\RedemptionServiceInterface;
use App\Traits\Controllers\ClampsLoyaltyPerPage;
use Illuminate\Http\Request;

class RedemptionController extends BaseController
{
    use ClampsLoyaltyPerPage;

    public function __construct(private RedemptionServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * POST /api/loyalty/redemptions
     */
    public function store(StoreRequest $request)
    {
        $redemption = $this->service->redeem(
            $request->user(),
            $request->input(),
            $request->filled('recipient_address'),
        );

        return (new RedemptionResource($redemption))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/loyalty/redemptions
     */
    public function index(Request $request)
    {
        $redemptions = $this->service->paginateForUser(
            $request->user()->getKey(),
            $request->filled('status') ? $request->input('status') : null,
            $this->loyaltyPerPage($request),
        );

        return RedemptionResource::collection($redemptions);
    }

    /**
     * GET /api/loyalty/redemptions/{redemption}
     *
     * Another customer's redemption answers 403.
     */
    public function show(Request $request, string $redemption)
    {
        return new RedemptionResource($this->service->findOwned($request->user()->getKey(), $redemption));
    }

    /**
     * POST /api/loyalty/redemptions/{redemption}/deliver
     */
    public function deliver(Request $request, string $redemption)
    {
        return new RedemptionResource($this->service->confirmDelivered($request->user()->getKey(), $redemption));
    }
}
