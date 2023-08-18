<?php

namespace Database\Seeders;

use App\Imports\ResellerSeederImport;
use App\Imports\UsersSeederImport;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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

        Excel::import(new UsersSeederImport, public_path('customers.xlsx'));
        // Excel::import(new ResellerSeederImport, public_path('resellers.xlsx'));
    }
}
