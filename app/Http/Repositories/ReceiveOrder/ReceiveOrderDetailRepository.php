<?php

namespace App\Http\Repositories\ReceiveOrder;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\ReceiveOrder\ReceiveOrderDetailRepositoryInterface;
use App\Models\ReceiveOrderDetail;

class ReceiveOrderDetailRepository extends BaseRepository implements ReceiveOrderDetailRepositoryInterface
{
    public function __construct(ReceiveOrderDetail $model)
    {
        parent::__construct($model);
    }
}
