<?php

namespace Database\Seeders;

use App\Imports\StockImport;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ImportStockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Excel::import(new StockImport(2), public_path('stock_booth1.xls'));
        Excel::import(new StockImport(3), public_path('stock_booth2.xls'));
    }
}
