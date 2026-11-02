<?php

namespace App\Http\Repositories\Warehouse;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Warehouse\WarehouseRepositoryInterface;
use App\Models\Warehouse;

class WarehouseRepository extends BaseRepository implements WarehouseRepositoryInterface
{
    public function __construct(Warehouse $model)
    {
        parent::__construct($model);
    }
}
