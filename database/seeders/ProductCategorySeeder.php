<?php

namespace Database\Seeders;

use App\Imports\ProductCategorySeederImport;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Excel::import(new ProductCategorySeederImport, public_path('product_categories.xlsx'));
    }
}
