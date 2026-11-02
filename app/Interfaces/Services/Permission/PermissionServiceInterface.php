<?php

namespace App\Interfaces\Services\Permission;

use App\Interfaces\Services\BaseServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PermissionServiceInterface extends BaseServiceInterface
{
    public function paginateIndex(mixed $perPage): LengthAwarePaginator;

    public function getPermissionNames(array $permissionIds = []): array;

    public function allPermissions(): mixed;
}
