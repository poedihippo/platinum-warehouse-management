<?php

namespace App\Http\Services\Supplier;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Supplier\SupplierRepositoryInterface;
use App\Interfaces\Services\Supplier\SupplierServiceInterface;

class SupplierService extends BaseService implements SupplierServiceInterface
{
    public function __construct(protected SupplierRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
