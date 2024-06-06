<?php

namespace Database\Seeders;

use App\Imports\ProductUnitSeederImport;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ProductUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Excel::import(new ProductUnitSeederImport, public_path('product_units_all.xlsx'));
        // Excel::import(new ProductUnitSeederImport, public_path('product_units1.xlsx'));
        // Excel::import(new ProductUnitSeederImport, public_path('product_units2.xlsx'));
    }
}
