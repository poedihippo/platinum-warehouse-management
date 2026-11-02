<?php

namespace App\Interfaces\Services\ProductBrand;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\ProductBrand;
use Illuminate\Http\UploadedFile;

interface ProductBrandServiceInterface extends BaseServiceInterface
{
    public function updateWithLogo(ProductBrand $productBrand, array $data, ?UploadedFile $logo = null): ProductBrand;
}
