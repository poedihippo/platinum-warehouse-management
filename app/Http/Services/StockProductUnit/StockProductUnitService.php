<?php

namespace App\Http\Services\StockProductUnit;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\StockProductUnit\StockProductUnitRepositoryInterface;
use App\Interfaces\Services\StockProductUnit\StockProductUnitServiceInterface;

class StockProductUnitService extends BaseService implements StockProductUnitServiceInterface
{
    public function __construct(protected StockProductUnitRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
