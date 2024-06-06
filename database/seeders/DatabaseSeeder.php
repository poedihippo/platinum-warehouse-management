<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // atiati pake seeder, pas deploy takut ke refresh db nya
        $this->call([
            SupplierSeeder::class,
            WarehouseSeeder::class,
            UomSeeder::class,
            ProductBrandSeeder::class,
            ProductCategorySeeder::class,
            ProductSeeder::class,
            ProductUnitSeeder::class,
            PermissionSeeder::class,
            AdminSeeder::class,
            UserSeeder::class,
            ResellerSeeder::class,
            // CustomerSeeder::class,
            VoucherCategorySeeder::class,
            SettingSeeder::class,
            UserDiscountSeeder::class,
            ImportStockSeeder::class,
        ]);
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
