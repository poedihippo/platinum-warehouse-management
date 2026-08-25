<?php

namespace Database\Seeders;

use App\Imports\CustomerSeederImport;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // User::factory()->count(10)->create(['type' => 3]); //customers

        Excel::import(new CustomerSeederImport, public_path('customers.xlsx'));
    }
}
