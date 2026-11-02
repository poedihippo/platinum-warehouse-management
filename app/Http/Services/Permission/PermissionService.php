<?php

namespace App\Http\Services\Permission;

use App\Helpers\PermissionsHelper;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Permission\PermissionRepositoryInterface;
use App\Interfaces\Services\Permission\PermissionServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PermissionService extends BaseService implements PermissionServiceInterface
{
    public function __construct(protected PermissionRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function paginateIndex(mixed $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateIndex($perPage);
    }

    /**
     * filter permissions ids
     */
    public function getPermissionNames(array $permissionIds = []): array
    {
        $pids = [];
        if (! is_array($permissionIds) || count($permissionIds) <= 0) {
            return $pids;
        }

        foreach ($permissionIds as $id) {
            $permission = $this->repository->findIdAndName($id);
            if ($permission) {
                $pids[] = $permission->name;

                $permissionNames = PermissionsHelper::getRelatedPermissions($permission->name);
                array_push($pids, ...$permissionNames);
            }
        }

        return $pids;
    }

    public function allPermissions(): mixed
    {
        return PermissionsHelper::getAllPermissions();
    }
}
