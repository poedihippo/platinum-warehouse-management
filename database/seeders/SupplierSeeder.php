<?php

namespace Database\Seeders;

use App\Enums\UserType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Supplier;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $supplier = Supplier::create([
            'name' => 'Tjut Nyak Dhien Corp',
            'email' => 'supplier@gmail.com',
            'phone' => '085777007002',
            'description' => 'Supplier berbagai pakan ikan bermutu dan berkualitas.',
            'address' => 'Jalan Penuh Kenangan Blok 38D No 25 Jawa Tengah.',
        ]);

        $supplier = Supplier::create([
            'name' => 'Platinum Adi Sentosa Corp',
            'email' => 'platinumadisentosa@gmail.com',
            'phone' => '08500000007',
            'description' => 'Supplier utama untuk pakan ikan dan vitamin ikan KOI.',
            'address' => 'Jalan Prominance Blok 38D No 25 Jawa Timur.',
        ]);
    }
}
