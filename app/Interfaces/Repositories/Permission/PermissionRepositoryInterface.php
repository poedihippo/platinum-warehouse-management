<?php

namespace App\Interfaces\Repositories\Permission;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PermissionRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateIndex(mixed $perPage): LengthAwarePaginator;

    public function findIdAndName(mixed $id): mixed;
}
