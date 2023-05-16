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

            'users_management_access' => [
                'users_access' => [
                    'user_view',
                    'user_create',
                    'user_edit',
                    'user_delete',
                ],
                'roles_access' => [
                    'role_view',
                    'role_create',
                    'role_edit',
                    'role_delete',
                ],
                'permissions_access' => [
                    'permission_view',
                    'permission_create',
                    'permission_edit',
                    'permission_delete',
                ],
            ],

            'products_management_access' => [
                'products_access' => [
                    'product_view',
                    'product_create',
                    'product_edit',
                    'product_delete',
                ],
                'product_categories_access' => [
                    'product_category_view',
                    'product_category_create',
                    'product_category_edit',
                    'product_category_delete',
                ],
                'product_brands_access' => [
                    'product_brand_view',
                    'product_brand_create',
                    'product_brand_edit',
                    'product_brand_delete',
                ],
                'product_units_access' => [
                    'product_unit_view',
                    'product_unit_create',
                    'product_unit_edit',
                    'product_unit_delete',
                ],
            ],

            'suppliers_access' => [
                'supplier_view',
                'supplier_create',
                'supplier_edit',
                'supplier_delete',
            ],

            'warehouses_access' => [
                'warehouse_view',
                'warehouse_create',
                'warehouse_edit',
                'warehouse_delete',
            ],

            'uoms_access' => [
                'uom_view',
                'uom_create',
                'uom_edit',
                'uom_delete',
            ],

            'receive_orders_access' => [
                'receive_order_view',
                'receive_order_create',
                'receive_order_edit',
                'receive_order_delete',
            ],

            // 'corporate_management_access' => [
                // 'companies_access' => [
                //     'companies_view',
                //     'companies_create',
                //     'companies_edit',
                //     'companies_delete',
                // ],
                // 'tenants_access' => [
                //     'tenants_view',
                //     'tenants_create',
                //     'tenants_edit',
                //     'tenants_delete',
                // ],
            // ],

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
        $guard = 'web';
        collect($subPermissions)->each(function ($permission, $key) use ($headSubPermissions, $guard) {
            if (is_array($permission)) {
                $hsp = Permission::firstOrCreate([
                    'name' => $key,
                    'guard_name' => $guard,
                    'parent_id' => $headSubPermissions->id
                ]);

                self::generateChilds($hsp, $permission);
            } else {
                $hsp = Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => $guard,
                    'parent_id' => $headSubPermissions->id
                ]);
            }

            return;
        });
    }
}
