<?php

namespace App\Interfaces\Services\ProductBrand;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\ProductBrand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface ProductBrandServiceInterface extends BaseServiceInterface
{
    public function updateWithLogo(ProductBrand $productBrand, mixed $data, ?UploadedFile $logo = null): ProductBrand;

    public function listForLoyalty(): Collection;

    public function uploadLogo(ProductBrand $productBrand, ?UploadedFile $logo): ProductBrand;
}
