<?php

namespace App\Http\Repositories\ReceiveOrder;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\ReceiveOrder\ReceiveOrderRepositoryInterface;
use App\Models\ReceiveOrder;

class ReceiveOrderRepository extends BaseRepository implements ReceiveOrderRepositoryInterface
{
    public function __construct(ReceiveOrder $model)
    {
        parent::__construct($model);
    }

    public function invoiceNoExists(mixed $invoiceNo): bool
    {
        return ReceiveOrder::where('invoice_no', $invoiceNo)->exists();
    }
}
