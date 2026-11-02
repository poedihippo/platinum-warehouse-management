<?php

namespace App\Http\Services\ProductCategory;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\ProductCategory\ProductCategoryRepositoryInterface;
use App\Interfaces\Services\ProductCategory\ProductCategoryServiceInterface;

class ProductCategoryService extends BaseService implements ProductCategoryServiceInterface
{
    public function __construct(protected ProductCategoryRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
