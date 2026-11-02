<?php

namespace App\Http\Services\Product;

use App\Http\Services\BaseService;
use App\Imports\ProductSeederImport;
use App\Interfaces\Repositories\Product\ProductRepositoryInterface;
use App\Interfaces\Services\Product\ProductServiceInterface;
use Maatwebsite\Excel\Facades\Excel;

class ProductService extends BaseService implements ProductServiceInterface
{
    public function __construct(protected ProductRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function import(mixed $file): void
    {
        Excel::import(new ProductSeederImport, $file);
    }
}
