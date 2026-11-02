<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Loyalty\Claim\StoreRequest;
use App\Http\Resources\Loyalty\ClaimResource;
use App\Interfaces\Services\Loyalty\Claim\ClaimServiceInterface;
use App\Traits\Controllers\ClampsLoyaltyPerPage;
use Illuminate\Http\Request;

class ClaimController extends BaseController
{
    use ClampsLoyaltyPerPage;

    public function __construct(private ClaimServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * POST /api/loyalty/claims
     */
    public function store(StoreRequest $request)
    {
        $claim = $this->service->submit(
            $request->user(),
            $request->input('invoice_number'),
            $request->file('invoice_photo'),
            $request->file('product_photos'),
        );

        return new ClaimResource($claim);
    }

    /**
     * GET /api/loyalty/claims
     */
    public function index(Request $request)
    {
        $claims = $this->service->paginateForUser(
            $request->user()->getKey(),
            $request->filled('status') ? $request->input('status') : null,
            $this->loyaltyPerPage($request),
        );

        return ClaimResource::collection($claims);
    }

    /**
     * GET /api/loyalty/claims/{claim}
     *
     * Another customer's claim answers 404, not 403.
     */
    public function show(Request $request, string $claim)
    {
        $model = $this->service->findForUser($request->user()->getKey(), $claim);

        if (! $model) {
            return response()->json(['message' => 'Klaim tidak ditemukan.'], 404);
        }

        return new ClaimResource($model);
    }
}
