<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Resources\Loyalty\PrizeCategoryResource;
use App\Models\Loyalty\PrizeCategory;

class PrizeCategoryController extends Controller
{
    /**
     * GET /api/loyalty/prize-categories
     *
     * Active categories only, for the prize catalog filter chips. The
     * frontend adds its own "Lainnya" (uncategorized) chip client-side.
     */
    public function index()
    {
        $categories = PrizeCategory::active()->orderBy('name')->get(['id', 'name']);

        return PrizeCategoryResource::collection($categories);
    }
}
