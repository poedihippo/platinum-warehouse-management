<?php

namespace App\Http\Repositories\Product;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Product\ProductRepositoryInterface;
use App\Models\Product;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }
}
