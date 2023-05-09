<?php

namespace App\Helpers;

use App\Models\Permission;

class PermissionsHelper
{
    public static function getAllPermissions()
    {
        return collect(static::adminPermissions());
        // ->mergeRecursive(static::superAdminPermissions());
    }

    public static function getAdminPermissionsData(): array
    {
        $persmissions = self::adminPermissions();

        $data = [];
        foreach ($persmissions as $key => $persmission) {
            if (is_array($persmission)) {
                $data[] = $key;
                foreach ($persmission as $key => $persmission) {
                    if (is_array($persmission)) {
                        $data[] = $key;

                        foreach ($persmission as $p) {
                            $data[] = $p;
                        }
                    } else {
                        $data[] = $persmission;
                    }
                }
            } else {
                $data[] = $persmission;
            }
        }
        return $data;
    }

    public static function adminPermissions(): array
    {
        return [
            'dashboard_access',

            'user_management_access' => [
                'users_access' => [
                    'users_view',
                    'users_create',
                    'users_edit',
                    'users_delete',
                ],
                'roles_access' => [
                    // 'roles_view',
                    'roles_create',
                    'roles_edit',
                    'roles_delete',
                ],
                'permissions_access' => [
                    'permissions_view',
                    'permissions_create',
                    'permissions_edit',
                    'permissions_delete',
                ],
            ],

            'corporate_management_access' => [
                // 'companies_access' => [
                //     'companies_view',
                //     'companies_create',
                //     'companies_edit',
                //     'companies_delete',
                // ],
                'tenants_access' => [
                    'tenants_view',
                    'tenants_create',
                    'tenants_edit',
                    'tenants_delete',
                ],
            ],

            'product_category_access' => [
                'tenants_access' => [
                    'tenants_view',
                    'tenants_create',
                    'tenants_edit',
                    'tenants_delete',
                ],
            ],

            // 'petty_cashes_management' => [
            //     'petty_cashes_access' => [
            //         'petty_cash_view',
            //         'petty_cash_create',
            //         'petty_cash_edit',
            //         'petty_cash_delete',
            //         'petty_cash_journals',
            //         'petty_cash_cash_in',
            //         'petty_cash_cash_out',
            //     ],
            //     'petty_cash_batches_access' => [
            //         'petty_cash_batch_view',
            //         'petty_cash_batch_create',
            //         'petty_cash_batch_edit',
            //         'petty_cash_batch_delete',
            //     ],
            // ],
        ];
    }

    // public static function superAdminPermissions(): array
    // {
    //     return [];
    // }

    public static function generateChilds(Permission $headSubPermissions, array $subPermissions)
    {
        // $guard = 'sanctum';
        $guard = '';
        collect($subPermissions)->each(function ($permission, $key) use ($headSubPermissions, $guard) {
            if (is_array($permission)) {
                $hsp = Permission::firstOrCreate([
                    'name' => $key,
                    // 'guard_name' => $guard,
                    'parent_id' => $headSubPermissions->id
                ]);

                self::generateChilds($hsp, $permission);
            } else {
                $hsp = Permission::firstOrCreate([
                    'name' => $permission,
                    // 'guard_name' => $guard,
                    'parent_id' => $headSubPermissions->id
                ]);
            }

            return;
        });
    }
}
