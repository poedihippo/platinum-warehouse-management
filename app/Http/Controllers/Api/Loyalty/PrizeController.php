<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Loyalty\PrizeResource;
use App\Interfaces\Services\Loyalty\Prize\PrizeServiceInterface;
use App\Traits\Controllers\ClampsLoyaltyPerPage;
use Illuminate\Http\Request;

class PrizeController extends BaseController
{
    use ClampsLoyaltyPerPage;

    public function __construct(private PrizeServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/loyalty/prizes
     *
     * Active prizes only. prize_category_id=none lists prizes without an active
     * category; affordable=1 keeps prizes within the customer's balance.
     */
    public function index(Request $request)
    {
        $prizes = $this->service->catalog(
            $request->filled('q') ? $request->input('q') : null,
            $request->filled('prize_category_id') ? $request->input('prize_category_id') : null,
            $request->input('sort'),
            $request->boolean('affordable') ? $request->user()->getKey() : null,
            $this->loyaltyPerPage($request),
        );

        return PrizeResource::collection($prizes);
    }

    /**
     * GET /api/loyalty/prizes/{prize}
     */
    public function show(string $prize)
    {
        $model = $this->service->findActive($prize);

        if (! $model) {
            return response()->json(['message' => 'Hadiah tidak ditemukan.'], 404);
        }

        return new PrizeResource($model);
    }
}
