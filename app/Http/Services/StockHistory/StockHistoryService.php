<?php

namespace App\Http\Services\StockHistory;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\StockHistory\StockHistoryRepositoryInterface;
use App\Interfaces\Services\StockHistory\StockHistoryServiceInterface;

class StockHistoryService extends BaseService implements StockHistoryServiceInterface
{
    public function __construct(protected StockHistoryRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
