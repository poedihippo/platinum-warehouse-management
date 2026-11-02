<?php

namespace App\Http\Repositories\ProductBrand;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\ProductBrand\ProductBrandRepositoryInterface;
use App\Models\ProductBrand;

class ProductBrandRepository extends BaseRepository implements ProductBrandRepositoryInterface
{
    public function __construct(ProductBrand $model)
    {
        parent::__construct($model);
    }
}
