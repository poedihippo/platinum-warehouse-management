<?php
namespace App\Services;

use App\Helpers\PermissionsHelper;
use App\Models\Permission;

class PermissionService
{

    /**
     * filter permissions ids
     *
     * @param array $permissionIds
     * @return array
     */
    public static function getPermissionNames(array $permissionIds = []): array
    {
        $pids = [];
        if (!is_array($permissionIds) || count($permissionIds) <= 0)
            return $pids;

        foreach ($permissionIds as $id) {
            $permission = Permission::find($id, ['id', 'name']);
            if ($permission) {
                $pids[] = $permission->name;

                $permissionNames = PermissionsHelper::getRelatedPermissions($permission->name);
                array_push($pids, ...$permissionNames);
            }
        }

        return $pids;
    }
}
