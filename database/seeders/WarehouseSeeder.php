<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Warehouse::create([
            'code' => 'Iconic',
            'name' => 'Iconic',
            'company_name' => 'PT. Platinum Adisentosa',
        ]);

        // Warehouse::create([
        //     'code' => 'WK1',
        //     'name' => 'Booth 1',
        //     'company_name' => 'Winkoi',
        // ]);

        // Warehouse::create([
        //     'code' => 'WK2',
        //     'name' => 'Booth 2',
        //     'company_name' => 'Winkoi',
        // ]);
    }
}
