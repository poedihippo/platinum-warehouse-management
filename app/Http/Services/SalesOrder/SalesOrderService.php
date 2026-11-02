<?php

namespace App\Http\Services\SalesOrder;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\SalesOrder\SalesOrderRepositoryInterface;
use App\Interfaces\Services\SalesOrder\SalesOrderServiceInterface;

class SalesOrderService extends BaseService implements SalesOrderServiceInterface
{
    public function __construct(protected SalesOrderRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
