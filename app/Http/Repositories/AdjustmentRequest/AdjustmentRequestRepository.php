<?php

namespace App\Http\Repositories\AdjustmentRequest;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\AdjustmentRequest\AdjustmentRequestRepositoryInterface;
use App\Models\AdjustmentRequest;

class AdjustmentRequestRepository extends BaseRepository implements AdjustmentRequestRepositoryInterface
{
    public function __construct(AdjustmentRequest $model)
    {
        parent::__construct($model);
    }
}
