<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Loyalty\PrizeCategoryResource;
use App\Interfaces\Services\Loyalty\PrizeCategory\PrizeCategoryServiceInterface;

class PrizeCategoryController extends BaseController
{
    public function __construct(private PrizeCategoryServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/loyalty/prize-categories
     */
    public function index()
    {
        return PrizeCategoryResource::collection($this->service->activeList());
    }
}
