<?php

namespace App\Interfaces\Services\Role;

use App\Interfaces\Services\BaseServiceInterface;
use Spatie\Permission\Models\Role;

interface RoleServiceInterface extends BaseServiceInterface
{
    public function createWithPermissions(?string $name, array $permissionIds = []): Role;

    public function updateWithPermissions(Role $role, ?string $name, array $permissionIds = []): Role;
}
