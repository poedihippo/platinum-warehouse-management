<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roleAdminAll = Role::create([
            'name' => 'Back Office Admin All',
        ]);
        $roleAdminAll->syncPermissions([
            'user_read',
            'warehouse_read',

            'receive_order_access',
            'receive_order_read',
            'receive_order_create',
            'receive_order_edit',
            'receive_order_delete',
            'receive_order_done',

            'sales_order_access',
            'sales_order_read',
            'sales_order_create',
            'sales_order_edit',
            'sales_order_delete',
            'sales_order_print',
            'sales_order_export_xml',
            'receive_order_verify_access',

            'delivery_order_access',
            'delivery_order_create',
            'delivery_order_edit',
            'delivery_order_delete',
            'delivery_order_print',
            'delivery_order_done',

            'stock_access',
            'stock_read',
            'stock_create',
            'stock_edit',
            'stock_delete',
            'stock_grouping',
            'stock_print',
            'stock_export',

            'stock_opname_access',
            'stock_opname_read',
            'stock_opname_create',
            'stock_opname_edit',
            'stock_opname_delete',
            'stock_opname_done',

            'stock_history_access',
            'stock_history_read',
            'stock_history_create',
            'stock_history_edit',
            'stock_history_delete',
            'stock_history_done',

            'product_access',
            'product_read',
            'product_create',
            'product_edit',
            'product_delete',

            'product_category_access',
            'product_category_read',
            'product_category_create',
            'product_category_edit',
            'product_category_delete',

            'product_brand_access',
            'product_brand_read',
            'product_brand_create',
            'product_brand_edit',
            'product_brand_delete',

            'product_unit_access',
            'product_unit_read',
            'product_unit_create',
            'product_unit_edit',
            'product_unit_delete',

            'product_unit_blacklist_access',
            'product_unit_blacklist_read',
            'product_unit_blacklist_create',
            'product_unit_blacklist_delete',
        ]);

        $roleAdminRO = Role::create([
            'name' => 'Back Office Admin - Receive Order',
        ]);
        $roleAdminRO->syncPermissions([
            'receive_order_access',
            'receive_order_read',
            'receive_order_create',
            'receive_order_edit',
            'receive_order_delete',
            'receive_order_done',
            'receive_order_verify_access',
        ]);

        $roleAdminSO = Role::create([
            'name' => 'Back Office Admin - Sales Order',
        ]);
        $roleAdminSO->syncPermissions([
            'sales_order_access',
            'sales_order_read',
            'sales_order_edit',
            'sales_order_create',
            'sales_order_delete',
            'sales_order_print',
            'sales_order_export_xml',
        ]);

        $roleAdminStock = Role::create([
            'name' => 'Back Office Admin - Stock',
        ]);
        $roleAdminStock->syncPermissions([
            'user_read',
            'warehouse_read',

            'stock_access',
            'stock_read',
            'stock_create',
            'stock_edit',
            'stock_delete',
            'stock_grouping',
            'stock_print',
            'stock_export',

            'stock_opname_access',
            'stock_opname_read',
            'stock_opname_create',
            'stock_opname_edit',
            'stock_opname_delete',
            'stock_opname_done',

            'stock_history_access',
            'stock_history_read',
            'stock_history_create',
            'stock_history_edit',
            'stock_history_delete',
            'stock_history_done',
        ]);

        $roleAdminWarehouse = Role::create([
            'name' => 'Warehouse',
        ]);
        $roleAdminWarehouse->syncPermissions([
            'user_read',
            'warehouse_read',

            'receive_order_access',
            'receive_order_read',
            'receive_order_create',
            'receive_order_edit',
            'receive_order_delete',
            'receive_order_done',
            'receive_order_verify_access',

            'delivery_order_access',
            'delivery_order_read',
            'delivery_order_read',
            'delivery_order_create',
            'delivery_order_edit',
            'delivery_order_delete',
            'delivery_order_print',
            'delivery_order_done',

            'stock_access',
            'stock_read',
            'stock_create',
            'stock_edit',
            'stock_delete',
            'stock_grouping',
            'stock_print',
            'stock_export',

            'stock_opname_access',
            'stock_opname_read',
            'stock_opname_create',
            'stock_opname_edit',
            'stock_opname_delete',
            'stock_opname_done',

            'stock_history_access',
            'stock_history_read',
            'stock_history_create',
            'stock_history_edit',
            'stock_history_delete',
            'stock_history_done',
        ]);

        $warehouseIds = Warehouse::get(['id'])->pluck('id') ?? [];

        // devi assign to Back Office Admin All
        $devi = User::create([
            'name' => 'Devi Platinum Backoffice',
            'code' => 'admin-devi',
            'email' => 'devi@platinumadisentosa.com',
            'password' => '12345678',
            'type' => UserType::Admin,
        ]);
        $devi->assignRole(1);
        $devi->warehouses()->sync($warehouseIds);

        // dina assign to Back Office Admin - Receive Order
        $dina = User::create([
            'name' => 'Dina Platinum Backoffice',
            'code' => 'admin-dina',
            'email' => 'dina@platinumadisentosa.com',
            'password' => '12345678',
            'type' => UserType::Admin,
        ]);
        $dina->assignRole(1);
        $dina->warehouses()->sync($warehouseIds);

        // Admin Backoffice assign to Back Office Admin - Sales Order
        $adminBackOffice = User::create([
            'name' => 'Admin Backoffice',
            'code' => 'admin-backoffice',
            'email' => 'admin@platinumadisentosa.com',
            'password' => '12345678',
            'type' => UserType::Admin,
        ]);
        $adminBackOffice->assignRole($roleAdminSO);
        $adminBackOffice->warehouses()->sync($warehouseIds);

        // devi assign to Warehouse
        $jhon = User::create([
            'name' => 'jhonxfaf0 Gudang',
            'code' => 'admin-jhon',
            'email' => 'jhonxfaf0@gmail.com',
            'password' => '12345678',
            'type' => UserType::Admin,
        ]);
        $jhon->assignRole($roleAdminWarehouse);
        $jhon->warehouses()->sync($warehouseIds);

        // devi assign to Warehouse
        $safrizal = User::create([
            'name' => 'safrizal arif Gudang',
            'code' => 'admin-safrizal',
            'email' => 'safrizalarif25@gmail.com',
            'password' => '12345678',
            'type' => UserType::Admin,
        ]);
        $safrizal->assignRole($roleAdminWarehouse);
        $safrizal->warehouses()->sync($warehouseIds);

        $warehouseWk1Id = Warehouse::where('code', 'WK1')->firstOrFail(['id'])->id;
        $warehouseWk2Id = Warehouse::where('code', 'WK2')->firstOrFail(['id'])->id;
        // $pameranWarehouseIds = [$warehouseWk1Id, $warehouseWk2Id];

        $roleKasirPameran = Role::create([
            'name' => 'Kasir Pameran',
        ]);
        $roleKasirPameran->syncPermissions([
            'user_read',
            'warehouse_read',
            'product_unit_read',
            'voucher_read',

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

            'payment_access',
            'payment_read',
            'payment_create',
            'payment_edit',
            'payment_delete',

            'adjustment_request_access',
            'adjustment_request_read',
            'adjustment_request_create',

            'stock_access',
            'stock_read',
            // 'stock_create',
            // 'stock_edit',
            // 'stock_delete',
            // 'stock_grouping',
            // 'stock_print',

            // 'stock_opname_access',
            // 'stock_opname_read',
            // 'stock_opname_create',
            // 'stock_opname_edit',
            // 'stock_opname_delete',
            // 'stock_opname_done',

            'stock_history_access',
            'stock_history_read',
            'stock_history_create',
            'stock_history_edit',
            // 'stock_history_delete',
            // 'stock_history_done',
        ]);

        $user = User::create([
            'name' => 'Winkoi 1',
            'code' => 'wk1',
            'email' => 'winkoi1@gmail.com',
            'password' => '12345678',
            'type' => UserType::Admin,
        ]);
        $user->assignRole($roleKasirPameran);
        $user->warehouses()->sync([$warehouseWk1Id]);

        $user = User::create([
            'name' => 'Winkoi 2',
            'code' => 'wk2',
            'email' => 'winkoi2@gmail.com',
            'password' => '12345678',
            'type' => UserType::Admin,
        ]);
        $user->assignRole($roleKasirPameran);
        $user->warehouses()->sync([$warehouseWk2Id]);


        $roleSpg = Role::create([
            'name' => 'Role SPG',
        ]);
        $roleSpg->syncPermissions([
            'user_read',
            'warehouse_read',
            'product_unit_read',
            'voucher_read',

            'order_access',
            'order_read',
            'order_create',
            'order_edit',
            'order_delete',
            // 'order_print',
            'order_export_xml',

            'payment_access',
            'payment_read',
            // 'payment_create',
            // 'payment_edit',
            // 'payment_delete',

            // 'stock_access',
            'stock_read',
            // 'stock_create',
            // 'stock_edit',
            // 'stock_delete',
            // 'stock_grouping',
            // 'stock_print',

            // 'stock_opname_access',
            // 'stock_opname_read',
            // 'stock_opname_create',
            // 'stock_opname_edit',
            // 'stock_opname_delete',
            // 'stock_opname_done',

            // 'stock_history_access',
            'stock_history_read',
            'stock_history_create',
            // 'stock_history_edit',
            // 'stock_history_delete',
            // 'stock_history_done',
        ]);

        $spgNames = [
            'tasya',
            'afifah',
            'sharren',
            'dhea',
            'vanessa',
            'gisel',
            'jordan',
        ];

        foreach ($spgNames as $spgName) {
            $user = User::create([
                'name' => $spgName,
                'code' => $spgName . '-spg',
                'email' => $spgName . '@gmail.com',
                'password' => $spgName . 'spg',
                'type' => UserType::Spg,
            ]);
            $user->assignRole($roleSpg);
            // $user->warehouses()->sync($pameranWarehouseIds);
        }
    }
}
