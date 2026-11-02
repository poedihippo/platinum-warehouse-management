<?php

namespace App\Interfaces\Repositories\SalesOrder;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\SalesOrderDetail;

interface SalesOrderRepositoryInterface extends BaseRepositoryInterface
{
    public function countFulfilledQty(SalesOrderDetail|int $salesOrderDetail): void;
}
