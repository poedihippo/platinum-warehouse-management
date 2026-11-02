<?php

namespace App\Http\Services\Uom;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Uom\UomRepositoryInterface;
use App\Interfaces\Services\Uom\UomServiceInterface;

class UomService extends BaseService implements UomServiceInterface
{
    public function __construct(protected UomRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
