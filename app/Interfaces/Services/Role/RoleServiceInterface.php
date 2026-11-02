<?php

namespace App\Interfaces\Services\Role;

use App\Interfaces\Services\BaseServiceInterface;
use Spatie\Permission\Models\Role;

interface RoleServiceInterface extends BaseServiceInterface
{
    public function createWithPermissions(mixed $name, mixed $permissionIds = []): Role;

    public function updateWithPermissions(Role $role, mixed $name, mixed $permissionIds = []): Role;
}
