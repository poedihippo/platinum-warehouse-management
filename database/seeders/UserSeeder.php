<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
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
            'receive_order_access',
            'receive_order_create',
            'receive_order_edit',
            'receive_order_delete',
            'receive_order_done',
            'sales_order_access',
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
            'stock_create',
            'stock_edit',
            'stock_delete',
            'stock_grouping',
            'stock_print',
            'stock_opname_access',
            'stock_opname_create',
            'stock_opname_edit',
            'stock_opname_delete',
            'stock_opname_done',
            'stock_history_access',
            'stock_history_create',
            'stock_history_edit',
            'stock_history_delete',
            'stock_history_done',
            'product_access',
            'product_create',
            'product_edit',
            'product_delete',
            'product_category_access',
            'product_category_create',
            'product_category_edit',
            'product_category_delete',
            'product_brand_access',
            'product_brand_create',
            'product_brand_edit',
            'product_brand_delete',
            'product_unit_access',
            'product_unit_create',
            'product_unit_edit',
            'product_unit_delete',
            'product_unit_blacklist_access',
            'product_unit_blacklist_create',
            'product_unit_blacklist_delete',
        ]);

        $roleAdminRO = Role::create([
            'name' => 'Back Office Admin - Receive Order',
        ]);
        $roleAdminRO->syncPermissions([
            'receive_order_access',
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
            'sales_order_edit',
            'sales_order_access',
            'sales_order_create',
            'sales_order_delete',
            'sales_order_print',
            'sales_order_export_xml',
        ]);

        $roleAdminStock = Role::create([
            'name' => 'Back Office Admin - Stock',
        ]);
        $roleAdminStock->syncPermissions([
            'stock_opname_edit',
            'stock_access',
            'stock_create',
            'stock_edit',
            'stock_delete',
            'stock_grouping',
            'stock_print',
            'stock_opname_access',
            'stock_opname_create',
            'stock_opname_delete',
            'stock_opname_done',
            'stock_history_access',
            'stock_history_create',
            'stock_history_edit',
            'stock_history_delete',
            'stock_history_done',
        ]);

        $roleAdminWarehouse = Role::create([
            'name' => 'Warehouse',
        ]);
        $roleAdminWarehouse->syncPermissions([
            'delivery_order_create',
            'receive_order_access',
            'receive_order_create',
            'receive_order_edit',
            'receive_order_delete',
            'receive_order_done',
            'receive_order_verify_access',

            'delivery_order_access',
            'delivery_order_read',
            'delivery_order_create',
            'delivery_order_edit',
            'delivery_order_delete',
            'delivery_order_print',
            'delivery_order_done',

            'stock_access',
            'stock_create',
            'stock_edit',
            'stock_delete',
            'stock_grouping',
            'stock_print',
            'stock_opname_access',
            'stock_opname_create',
            'stock_opname_edit',
            'stock_opname_delete',
            'stock_opname_done',
            'stock_history_access',
            'stock_history_create',
            'stock_history_edit',
            'stock_history_delete',
            'stock_history_done',
        ]);

        // devi assign to Back Office Admin All
        $devi = User::create([
            'name' => 'Devi Platinum Backoffice',
            'code' => 'admin-devi',
            'email' => 'devi@platinumadisentosa.com',
            'password' => bcrypt('12345678'),
            'type' => UserType::Admin,
        ]);
        $devi->assignRole(1);

        // Admin Backoffice assign to Back Office Admin - Sales Order
        $adminBackOffice = User::create([
            'name' => 'Admin Backoffice',
            'code' => 'admin-backoffice',
            'email' => 'admin@platinumadisentosa.com',
            'password' => bcrypt('12345678'),
            'type' => UserType::Admin,
        ]);
        $adminBackOffice->assignRole($roleAdminSO);

        // dina assign to Back Office Admin - Receive Order
        $dina = User::create([
            'name' => 'Dina Platinum Backoffice',
            'code' => 'admin-dina',
            'email' => 'dina@platinumadisentosa.com',
            'password' => bcrypt('12345678'),
            'type' => UserType::Admin,
        ]);
        $dina->assignRole($roleAdminRO);

        // devi assign to Warehouse
        $jhon = User::create([
            'name' => 'jhonxfaf0 Gudang',
            'code' => 'admin-jhon',
            'email' => 'jhonxfaf0@gmail.com',
            'password' => bcrypt('12345678'),
            'type' => UserType::Admin,
        ]);
        $jhon->assignRole($roleAdminWarehouse);

        // devi assign to Warehouse
        $safrizal = User::create([
            'name' => 'safrizal arif Gudang',
            'code' => 'admin-safrizal',
            'email' => 'safrizalarif25@gmail.com',
            'password' => bcrypt('12345678'),
            'type' => UserType::Admin,
        ]);
        $safrizal->assignRole($roleAdminWarehouse);
    }
}
