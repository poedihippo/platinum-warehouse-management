<?php

namespace Database\Seeders;

use App\Imports\CustomerEventSeederImport;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class CustomerEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Excel::import(new CustomerEventSeederImport, public_path('customer_event.xlsx'));
    }
}
