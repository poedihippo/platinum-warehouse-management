<?php

namespace App\Http\Services\Stock;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Stock\StockRepositoryInterface;
use App\Interfaces\Services\Stock\StockServiceInterface;

class StockService extends BaseService implements StockServiceInterface
{
    public function __construct(protected StockRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
