<?php

namespace App\Http\Repositories\ProductCategory;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\ProductCategory\ProductCategoryRepositoryInterface;
use App\Models\ProductCategory;

class ProductCategoryRepository extends BaseRepository implements ProductCategoryRepositoryInterface
{
    public function __construct(ProductCategory $model)
    {
        parent::__construct($model);
    }
}
