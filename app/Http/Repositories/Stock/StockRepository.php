<?php

namespace App\Http\Repositories\Stock;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Stock\StockRepositoryInterface;
use App\Models\Stock;

class StockRepository extends BaseRepository implements StockRepositoryInterface
{
    public function __construct(Stock $model)
    {
        parent::__construct($model);
    }

    public function findForProductLookup(string $id): ?Stock
    {
        return $this->query()->select('id', 'stock_product_unit_id', 'expired_date')->find($id);
    }
}
