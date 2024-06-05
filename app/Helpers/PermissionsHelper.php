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
            'user_access' => [
                'user_read',
                'user_create',
                'user_edit',
                'user_delete',
            ],
            'user_discount_access' => [
                'user_discount_read',
                'user_discount_create',
                'user_discount_edit',
                'user_discount_delete',
            ],
            'role_access' => [
                'role_read',
                'role_create',
                'role_edit',
                'role_delete',
            ],
            'permission_access' => [
                'permission_read',
                'permission_create',
                'permission_edit',
                'permission_delete',
            ],

            'product_access' => [
                'product_read',
                'product_create',
                'product_edit',
                'product_delete',
            ],
            'product_category_access' => [
                'product_category_read',
                'product_category_create',
                'product_category_edit',
                'product_category_delete',
            ],
            'product_brand_access' => [
                'product_brand_read',
                'product_brand_create',
                'product_brand_edit',
                'product_brand_delete',
            ],
            'product_unit_access' => [
                'product_unit_read',
                'product_unit_create',
                'product_unit_edit',
                'product_unit_delete',
            ],

            'supplier_access' => [
                'supplier_read',
                'supplier_create',
                'supplier_edit',
                'supplier_delete',
            ],

            'warehouse_access' => [
                'warehouse_read',
                'warehouse_create',
                'warehouse_edit',
                'warehouse_delete',
            ],

            'uom_access' => [
                'uom_read',
                'uom_create',
                'uom_edit',
                'uom_delete',
            ],

            'receive_order_access' => [
                'receive_order_read',
                'receive_order_create',
                'receive_order_edit',
                'receive_order_delete',
                'receive_order_done',
            ],

            'sales_order_access' => [
                'sales_order_read',
                'sales_order_create',
                'sales_order_edit',
                'sales_order_delete',
                'sales_order_print',
                'sales_order_export_xml',
                'receive_order_verify_access',
            ],

            'invoice_access' => [
                'invoice_read',
                'invoice_create',
                'invoice_edit',
                'invoice_delete',
                // 'invoice_print',
                'invoice_export_xml',
            ],

            // for spg
            'order_access' => [
                'order_read',
                'order_create',
                'order_edit',
                'order_delete',
                // 'order_print',
                'order_export_xml',
            ],

            'delivery_order_access' => [
                'delivery_order_read',
                'delivery_order_create',
                'delivery_order_edit',
                'delivery_order_delete',
                'delivery_order_print',
                'delivery_order_done',
            ],

            'stock_access' => [
                'stock_read',
                'stock_create',
                'stock_edit',
                'stock_delete',
                'stock_grouping',
                'stock_print',
            ],

            'stock_opname_access' => [
                'stock_opname_read',
                'stock_opname_create',
                'stock_opname_edit',
                'stock_opname_delete',
                'stock_opname_done',
            ],

            'stock_history_access' => [
                'stock_history_read',
                'stock_history_create',
                'stock_history_edit',
                'stock_history_delete',
                'stock_history_done',
            ],

            'adjustment_request_access' => [
                'adjustment_request_read',
                'adjustment_request_create',
                'adjustment_request_edit',
                'adjustment_request_delete',
                'adjustment_request_approve',
            ],

            'product_unit_blacklist_access' => [
                'product_unit_blacklist_read',
                'product_unit_blacklist_create',
                'product_unit_blacklist_delete',
            ],

            'setting_access' => [
                'setting_read',
                'setting_edit',
            ],

            'payment_access' => [
                'payment_read',
                'payment_create',
                'payment_edit',
                'payment_delete',
            ],

            'voucher_access' => [
                'voucher_read',
                'voucher_create',
                'voucher_edit',
                'voucher_delete',
                'voucher_import',
            ],
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

    public static function getMyPermissions()
    {
        /** @var \App\Models\User $user */
        $user = auth('sanctum')->user();
        $allPermissions = [];
        if ($user->hasRole('admin')) {
            foreach (self::getAllPermissions() as $parent => $childs) {
                if (is_array($childs)) {
                    $allPermissions[$parent][$parent] = true;
                    foreach ($childs as $child) {
                        $allPermissions[$parent][$child] = true;
                    }
                } else {
                    $allPermissions[$childs] = true;
                }
            }
        } else {
            $myPermissions = $user?->getAllPermissions()?->pluck('name') ?? collect([]);
            foreach (self::getAllPermissions() as $parent => $childs) {
                if (is_array($childs)) {
                    $allPermissions[$parent][$parent] = $myPermissions->search($parent) === false ? false : true;
                    foreach ($childs as $child) {
                        $allPermissions[$parent][$child] = $myPermissions->search($child) === false ? false : true;
                    }
                } else {
                    $allPermissions[$childs] = $myPermissions->search($childs) === false ? false : true;
                }
            }
        }

        return $allPermissions;
    }

    public static function getRelatedPermissions(string $permission): array
    {
        return match ($permission) {
            'receive_order_access' => ['stock_read'],
            'stock_access' => ['product_category_read', 'product_brand_read', 'warehouse_read'],
            'sales_order_access' => [
                'product_unit_read',
                'warehouse_read',
                'user_access',
                'payment_access',
                'payment_read',
                'payment_create',
                'payment_edit',
                'payment_delete',

                'invoice_access',
                'invoice_read',
                'invoice_create',
                'invoice_edit',
                'invoice_delete',
                // 'invoice_print',
                'invoice_export_xml',

                'order_access',
                'order_read',
                'order_create',
                'order_edit',
                'order_delete',
                // 'order_print',
                'order_export_xml',
            ],
            'delivery_order_access' => ['sales_order_read', 'payment_access', 'payment_read', 'payment_create', 'payment_edit', 'payment_delete'],
            'product_access' => ['product_category_read', 'product_brand_read', 'product_unit_read'],
            'user_access' => ['role_read'],
            'order_access' => ['product_unit_read'],
            default => [],
        };
    }
}
