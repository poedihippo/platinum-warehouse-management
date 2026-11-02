<?php

namespace App\Http\Services\Warehouse;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Warehouse\WarehouseRepositoryInterface;
use App\Interfaces\Services\Warehouse\WarehouseServiceInterface;

class WarehouseService extends BaseService implements WarehouseServiceInterface
{
    public function __construct(protected WarehouseRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
