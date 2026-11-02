<?php

namespace App\Interfaces\Repositories\Stock;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\Stock;

interface StockRepositoryInterface extends BaseRepositoryInterface
{
    public function findForProductLookup(string $id): ?Stock;
}
