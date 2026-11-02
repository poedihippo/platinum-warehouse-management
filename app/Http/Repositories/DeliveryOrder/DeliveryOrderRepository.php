<?php

namespace App\Http\Repositories\DeliveryOrder;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\DeliveryOrder\DeliveryOrderRepositoryInterface;
use App\Models\DeliveryOrder;

class DeliveryOrderRepository extends BaseRepository implements DeliveryOrderRepositoryInterface
{
    public function __construct(DeliveryOrder $model)
    {
        parent::__construct($model);
    }
}
