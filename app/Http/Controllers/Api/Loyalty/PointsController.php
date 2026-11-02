<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Loyalty\PointsTransactionResource;
use App\Interfaces\Services\Loyalty\PointsTransaction\PointsServiceInterface;
use Illuminate\Http\Request;

class PointsController extends BaseController
{
    public function __construct(private PointsServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/loyalty/points/balance
     */
    public function balance(Request $request)
    {
        return response()->json($this->service->balance($request->user()->getKey()));
    }

    /**
     * GET /api/loyalty/points/transactions
     */
    public function transactions(Request $request)
    {
        $transactions = $this->service->transactions($request->user()->getKey());

        return PointsTransactionResource::collection($transactions);
    }
}
