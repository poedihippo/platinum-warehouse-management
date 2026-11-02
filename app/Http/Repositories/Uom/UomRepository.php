<?php

namespace App\Http\Repositories\Uom;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Uom\UomRepositoryInterface;
use App\Models\Uom;

class UomRepository extends BaseRepository implements UomRepositoryInterface
{
    public function __construct(Uom $model)
    {
        parent::__construct($model);
    }
}
