<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Loyalty\AdminProductUnitPointsResource;
use App\Http\Resources\ProductUnitResource;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductUnitPointsController extends Controller
{
    private const PERMISSION = 'manage loyalty points';

    /**
     * GET /api/admin/loyalty/points
     *
     * Units currently in the loyalty points program. Query: q (searches
     * name/code), per_page, page. Default sort: product name asc.
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $query = ProductUnit::with(['product:id,name', 'uom:id,name'])
            ->where('points_per_unit', '>', 0)
            ->orderBy(Product::select('name')->whereColumn('products.id', 'product_units.product_id'));

        if ($request->filled('q')) {
            $query->search($request->input('q'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        return AdminProductUnitPointsResource::collection($query->paginate($perPage));
    }

    /**
     * GET /api/admin/loyalty/points/eligible?q=...
     *
     * Typeahead for the "add product" flow: units curated as
     * loyalty_eligible that aren't in the program yet (points_per_unit
     * still 0). Capped at 20, not paginated.
     */
    public function eligible(Request $request)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $query = ProductUnit::with(['product:id,name', 'uom:id,name'])
            ->where('loyalty_eligible', true)
            ->where('points_per_unit', 0);

        if ($request->filled('q')) {
            $query->search($request->input('q'));
        }

        $units = $query->orderBy('name')->limit(20)->get();

        return AdminProductUnitPointsResource::collection($units);
    }

    /**
     * PATCH /api/admin/product-units/{productUnit}/points
     *
     * Inline-edit a product unit's loyalty points_per_unit value.
     */
    public function update(Request $request, ProductUnit $productUnit)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        if ($response = $this->applyPoints($request, $productUnit)) {
            return $response;
        }

        return new ProductUnitResource($productUnit);
    }

    /**
     * PATCH /api/admin/loyalty/points/{productUnit}
     *
     * Same write as update() above, reached from the loyalty admin
     * screen (inside the admin/loyalty route prefix, so a loyalty-only
     * token can reach it), returning the points-management row shape
     * instead of the full ProductUnitResource.
     */
    public function updateLoyaltyPoints(Request $request, ProductUnit $productUnit)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        if ($response = $this->applyPoints($request, $productUnit)) {
            return $response;
        }

        return new AdminProductUnitPointsResource($productUnit->load(['product:id,name', 'uom:id,name']));
    }

    /**
     * Validates and applies points_per_unit. Returns a JSON error
     * response if the write was rejected, else null on success.
     * Setting 0 removes the unit from the program — there is no delete
     * route, this is how "remove" works.
     */
    private function applyPoints(Request $request, ProductUnit $productUnit)
    {
        $validated = $request->validate([
            'points_per_unit' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        // An ineligible unit must never get points, regardless of which
        // route this was reached through.
        if (!$productUnit->loyalty_eligible) {
            return response()->json([
                'message' => 'Product unit ini tidak memenuhi syarat untuk program loyalty.',
            ], 422);
        }

        Log::info('Product unit points updated', [
            'product_unit_id' => $productUnit->id,
            'old_value' => $productUnit->getOriginal('points_per_unit'),
            'new_value' => $validated['points_per_unit'],
            'changed_by' => auth()->id(),
        ]);

        // points_per_unit is intentionally absent from ProductUnit::$fillable.
        $productUnit->points_per_unit = $validated['points_per_unit'];
        $productUnit->save();

        return null;
    }

    /**
     * Returns a 403 JSON response when the admin lacks the loyalty-points
     * permission, else null.
     */
    private function denyUnlessAuthorized(Request $request)
    {
        if ($request->user()?->can(self::PERMISSION)) {
            return null;
        }

        return response()->json([
            'message' => 'You are not authorized to manage loyalty points.',
        ], 403);
    }
}
