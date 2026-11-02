<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Loyalty\AdminProductUnitSearchResource;
use App\Interfaces\Services\ProductUnit\ProductUnitServiceInterface;
use App\Traits\Controllers\DeniesUnlessCan;
use Illuminate\Http\Request;

class ProductUnitSearchController extends BaseController
{
    use DeniesUnlessCan;

    private const PERMISSION = 'review claims';

    public function __construct(private ProductUnitServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/admin/loyalty/product-units?q=...
     *
     * Autocomplete source for claim line-item entry. Only units with a
     * positive points_per_unit are returned — a unit that earns no points
     * can't be a claim line item (see ClaimReviewController::addLineItem).
     * Gated with the same permission as the claims routes it feeds.
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, 'Anda tidak memiliki izin untuk meninjau klaim.')) {
            return $denied;
        }

        $units = $this->service->searchForClaims($request->filled('q') ? $request->input('q') : null);

        return AdminProductUnitSearchResource::collection($units);
    }
}
