<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductBrand;
use App\Models\ProductUnit;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $productCategory = ProductCategory::create([
            'name' => 'Pakan ikan air tawar',
            'description' => 'Pakan ikan air tawar merupakan pakan premium dari JPD yang di import oleh PT Platinum Adi Sentosa. Pakan ini mengandung beta glucan dan ragi toruta yang berfungsi menjaga daya tahan tubuh goldfish dan juga mempercepaat pertumbuhannya.'
        ]);

        $productBrand = ProductBrand::create([
            'name' => 'Mizuho',
            'description' => 'Pakan Ikan merk Mizuho'
        ]);

        $product = Product::create([
            'name' => 'Mizuho Wesi-wesi',
            'description' => 'Mizuho Wesi-wesi Mizuho Wesi-wesi',
            'product_category_id' => $productCategory->id,
            'product_brand_id' => $productBrand->id
        ]);

        ProductUnit::create([
            'uom_id' => 1,
            'code' => 'MIGF06',
            'name' => 'Mizuho GF Sinking @ 20kg',
            'product_id' => $product->id,
            'price' => '1000000',
            'description' => 'Mizuho GF Sinking @ 20kg',
        ]);

        ProductUnit::create([
            'uom_id' => 1,
            'code' => 'MISS01',
            'name' => 'Mizuho Sinking S @ 20kg',
            'product_id' => $product->id,
            'price' => '1193100',
            'description' => 'Mizuho Sinking S @ 20kg',
        ]);

        ProductUnit::create([
            'uom_id' => 1,
            'code' => 'MISM01',
            'name' => 'Mizuho Sinking M @ 20kg',
            'product_id' => $product->id,
            'price' => '1193100',
            'description' => 'Mizuho Sinking M @ 20kg',
        ]);

        ProductUnit::create([
            'uom_id' => 1,
            'code' => 'MISL01',
            'name' => 'Mizuho Sinking L @ 20kg',
            'product_id' => $product->id,
            'price' => '1193100',
            'description' => 'Mizuho Sinking L @ 20kg',
        ]);
    }
}
