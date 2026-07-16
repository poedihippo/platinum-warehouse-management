<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Loyalty\Admin\UploadBrandLogoRequest;
use App\Http\Resources\Loyalty\AdminBrandResource;
use App\Models\ProductBrand;
use Illuminate\Http\Request;

class BrandManagementController extends Controller
{
    private const PERMISSION = 'manage brands';

    /**
     * GET /api/admin/loyalty/brands
     * All brands (~16, not paginated) for the logo-management screen.
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $brands = ProductBrand::orderBy('name')->get(['id', 'name', 'logo_path']);

        return AdminBrandResource::collection($brands);
    }

    /**
     * POST /api/admin/loyalty/brands/{productBrand}/logo
     * Dedicated upload-only endpoint — POST, not PATCH, so a multipart
     * body never needs Laravel's _method spoofing.
     */
    public function uploadLogo(UploadBrandLogoRequest $request, ProductBrand $productBrand)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        if ($request->hasFile('logo')) {
            $productBrand->update(['logo_path' => $productBrand->storeLogo($request->file('logo'))]);
        }

        return new AdminBrandResource($productBrand);
    }

    /**
     * Returns a 403 JSON response when the admin lacks the brand-management
     * permission, else null. Mirrors PrizeManagementController.
     */
    private function denyUnlessAuthorized(Request $request)
    {
        if ($request->user()?->can(self::PERMISSION)) {
            return null;
        }

        return response()->json([
            'message' => 'Anda tidak memiliki izin untuk mengelola brand.',
        ], 403);
    }
}
