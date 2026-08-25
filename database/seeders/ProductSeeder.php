<?php

namespace Database\Seeders;

use App\Imports\ProductSeederImport;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Excel::import(new ProductSeederImport, public_path('products.xlsx'));
    }
}
