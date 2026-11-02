<?php

namespace App\Http\Services\ProductBrand;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\ProductBrand\ProductBrandRepositoryInterface;
use App\Interfaces\Services\ProductBrand\ProductBrandServiceInterface;
use App\Models\ProductBrand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class ProductBrandService extends BaseService implements ProductBrandServiceInterface
{
    public function __construct(protected ProductBrandRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function updateWithLogo(ProductBrand $productBrand, array $data, ?UploadedFile $logo = null): ProductBrand
    {
        $data = collect($data)->except('logo')->all();

        if ($logo) {
            $data['logo_path'] = $productBrand->storeLogo($logo);
        }

        $this->repository->update($productBrand, $data);

        return $productBrand;
    }

    public function listForLoyalty(): Collection
    {
        return $this->repository->query()->orderBy('name')->get(['id', 'name', 'logo_path']);
    }

    public function uploadLogo(ProductBrand $productBrand, ?UploadedFile $logo): ProductBrand
    {
        if ($logo) {
            $productBrand->update(['logo_path' => $productBrand->storeLogo($logo)]);
        }

        return $productBrand;
    }
}
