<?php

namespace App\Http\Repositories\StockTransfer;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\StockTransfer\StockTransferRepositoryInterface;
use App\Models\StockTransfer;

class StockTransferRepository extends BaseRepository implements StockTransferRepositoryInterface
{
    public function __construct(StockTransfer $model)
    {
        parent::__construct($model);
    }
}
