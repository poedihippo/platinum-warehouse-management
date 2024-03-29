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
            'bag',
            'box',
            'dus',
            'gr',
            'kg',
            'lbr',
            'pak',
            'pcs',
            'rol',
            'sak',
            'set',
            'sachet',
        ])->each(fn($uom) => Uom::create(['name' => trim($uom)]));
    }
}
