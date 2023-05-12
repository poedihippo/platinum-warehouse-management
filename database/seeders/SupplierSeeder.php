<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Supplier::create([
            'code' => 'V-004',
            'name' => 'Tjut Nyak Dhien Corp',
            'email' => 'supplier@gmail.com',
            'phone' => '085777007002',
            'description' => 'Supplier berbagai pakan ikan bermutu dan berkualitas.',
            'address' => 'Jalan Penuh Kenangan Blok 38D No 25 Jawa Tengah.',
        ]);

        Supplier::create([
            'code' => 'V-001',
            'name' => 'Platinum Adi Sentosa Corp',
            'email' => 'platinumadisentosa@gmail.com',
            'phone' => '08500000007',
            'description' => 'Supplier utama untuk pakan ikan dan vitamin ikan KOI.',
            'address' => 'Jalan Prominance Blok 38D No 25 Jawa Timur.',
        ]);
    }
}
