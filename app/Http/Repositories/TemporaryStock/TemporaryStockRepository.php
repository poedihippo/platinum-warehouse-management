<?php

namespace App\Http\Repositories\TemporaryStock;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\TemporaryStock\TemporaryStockRepositoryInterface;
use App\Models\TemporaryStock;

class TemporaryStockRepository extends BaseRepository implements TemporaryStockRepositoryInterface
{
    public function __construct(TemporaryStock $model)
    {
        parent::__construct($model);
    }
}
