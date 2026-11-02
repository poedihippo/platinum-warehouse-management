<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\ProductBrand\UploadLogoRequest;
use App\Http\Resources\Loyalty\AdminBrandResource;
use App\Interfaces\Services\ProductBrand\ProductBrandServiceInterface;
use App\Models\ProductBrand;
use App\Traits\Controllers\DeniesUnlessCan;
use Illuminate\Http\Request;

class BrandManagementController extends BaseController
{
    use DeniesUnlessCan;

    private const PERMISSION = 'manage brands';

    private const DENIED_MESSAGE = 'Anda tidak memiliki izin untuk mengelola brand.';

    public function __construct(private ProductBrandServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/admin/loyalty/brands
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        return AdminBrandResource::collection($this->service->listForLoyalty());
    }

    /**
     * POST /api/admin/loyalty/brands/{productBrand}/logo
     */
    public function uploadLogo(UploadLogoRequest $request, ProductBrand $productBrand)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $productBrand = $this->service->uploadLogo($productBrand, $request->hasFile('logo') ? $request->file('logo') : null);

        return new AdminBrandResource($productBrand);
    }
}
