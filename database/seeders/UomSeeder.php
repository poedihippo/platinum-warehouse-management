<?php

namespace Database\Seeders;

use App\Models\Uom;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        collect([
            'pack',
            'sak',
            'kardus',
            'kg',
            'gr',
        ])->each(fn ($uom) => Uom::create(['name' => $uom]));
    }
}
