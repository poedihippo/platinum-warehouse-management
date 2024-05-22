<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Warehouse;

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

        Warehouse::create([
            'code' => 'WK1',
            'name' => 'Winkoi 1',
            'company_name' => 'Winkoi',
        ]);

        Warehouse::create([
            'code' => 'WK2',
            'name' => 'Winkoi 2',
            'company_name' => 'Winkoi',
        ]);
    }
}
