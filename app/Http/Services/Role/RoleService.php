<?php

namespace App\Http\Services\Role;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Role\RoleRepositoryInterface;
use App\Interfaces\Services\Permission\PermissionServiceInterface;
use App\Interfaces\Services\Role\RoleServiceInterface;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleService extends BaseService implements RoleServiceInterface
{
    public function __construct(
        protected RoleRepositoryInterface $repository,
        protected PermissionServiceInterface $permissionService,
    ) {
        parent::__construct($repository);
    }

    public function createWithPermissions(mixed $name, mixed $permissionIds = []): Role
    {
        $permissionNames = $this->permissionService->getPermissionNames($permissionIds);
        $role = DB::transaction(function () use ($name, $permissionNames) {
            $role = new Role();
            $role->name = $name;
            $role->guard_name = 'web';
            $role->save();

            $role->syncPermissions($permissionNames ?? []);

            return $role;
        });

        cache()->flush();

        return $role;
    }

    public function updateWithPermissions(Role $role, mixed $name, mixed $permissionIds = []): Role
    {
        $permissionNames = $this->permissionService->getPermissionNames($permissionIds);
        $role = DB::transaction(function () use ($role, $name, $permissionNames) {
            $role->name = $name;
            $role->save();

            $role->syncPermissions($permissionNames ?? []);

            return $role;
        });

        cache()->flush();

        return $role;
    }
}
