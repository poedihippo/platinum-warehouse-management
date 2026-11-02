<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Loyalty\AdminProductUnitPointsResource;
use App\Http\Resources\ProductUnit\ProductUnitResource;
use App\Interfaces\Services\ProductUnit\ProductUnitServiceInterface;
use App\Models\ProductUnit;
use App\Traits\Controllers\DeniesUnlessCan;
use Illuminate\Http\Request;

class ProductUnitPointsController extends BaseController
{
    use DeniesUnlessCan;

    private const PERMISSION = 'manage loyalty points';

    private const DENIED_MESSAGE = 'You are not authorized to manage loyalty points.';

    public function __construct(private ProductUnitServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/admin/loyalty/points
     *
     * The whole curated whitelist, edited in place from one table: every
     * loyalty_eligible unit (most start at 0) plus any stray unit that
     * already has points_per_unit > 0 despite not being eligible, so an
     * admin can see and zero it out. Query: q (searches name/code),
     * per_page, page. Sort: points > 0 first, then product name asc.
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $perPage = (int) $request->input('per_page', 200);
        $perPage = $perPage > 0 && $perPage <= 250 ? $perPage : 200;

        $productUnits = $this->service->paginateLoyaltyPoints(
            $request->filled('q') ? $request->input('q') : null,
            $perPage,
        );

        return AdminProductUnitPointsResource::collection($productUnits);
    }

    /**
     * PATCH /api/admin/product-units/{productUnit}/points
     *
     * Inline-edit a product unit's loyalty points_per_unit value.
     */
    public function update(Request $request, ProductUnit $productUnit)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $this->applyPoints($request, $productUnit);

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
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $this->applyPoints($request, $productUnit);

        return new AdminProductUnitPointsResource($productUnit->load(['product:id,name', 'uom:id,name']));
    }

    private function applyPoints(Request $request, ProductUnit $productUnit): void
    {
        $validated = $request->validate([
            'points_per_unit' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $this->service->applyPoints($productUnit, $validated['points_per_unit']);
    }
}
