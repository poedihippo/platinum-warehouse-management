<?php

namespace App\Interfaces\Repositories\ReceiveOrder;

use App\Interfaces\Repositories\BaseRepositoryInterface;

interface ReceiveOrderRepositoryInterface extends BaseRepositoryInterface
{
    public function invoiceNoExists(mixed $invoiceNo): bool;
}
