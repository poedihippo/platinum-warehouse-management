<?php

namespace Database\Seeders;

use App\Imports\ResellerSeederImport;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ResellerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Excel::import(new ResellerSeederImport, public_path('resellers.xlsx'));
    }
}
