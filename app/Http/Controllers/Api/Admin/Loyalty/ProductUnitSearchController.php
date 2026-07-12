<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Resources\Loyalty\AdminProductUnitSearchResource;
use App\Models\ProductUnit;
use Illuminate\Http\Request;

class ProductUnitSearchController extends Controller
{
    /**
     * GET /api/admin/loyalty/product-units?q=...
     *
     * Autocomplete source for claim line-item entry. Only units with a
     * positive points_per_unit are returned — a unit that earns no points
     * can't be a claim line item (see ClaimReviewController::addLineItem).
     */
    public function index(Request $request)
    {
        $query = ProductUnit::with('product:id,name')
            ->where('points_per_unit', '>', 0);

        if ($request->filled('q')) {
            $query->search($request->input('q'));
        }

        $units = $query->orderBy('name')->limit(20)->get();

        return AdminProductUnitSearchResource::collection($units);
    }
}
