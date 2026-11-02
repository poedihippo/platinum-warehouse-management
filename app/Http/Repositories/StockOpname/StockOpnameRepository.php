<?php

namespace App\Http\Repositories\StockOpname;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\StockOpname\StockOpnameRepositoryInterface;
use App\Models\StockOpname;

class StockOpnameRepository extends BaseRepository implements StockOpnameRepositoryInterface
{
    public function __construct(StockOpname $model)
    {
        parent::__construct($model);
    }
}
