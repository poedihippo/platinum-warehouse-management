<?php

namespace Database\Seeders;

use App\Imports\ProductBrandSeederImport;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ProductBrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Excel::import(new ProductBrandSeederImport, public_path('product_brands.xlsx'));
    }
}
