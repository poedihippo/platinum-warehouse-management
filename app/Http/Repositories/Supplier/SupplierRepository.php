<?php

namespace App\Http\Repositories\Supplier;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Supplier\SupplierRepositoryInterface;
use App\Models\Supplier;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    public function __construct(Supplier $model)
    {
        parent::__construct($model);
    }
}
