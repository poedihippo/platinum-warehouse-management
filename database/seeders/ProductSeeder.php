<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
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
            'name' => 'JPD Kingyo Zen Gain Weight & Color Enhancer',
            'description' => 'JPD Goldfish Food Kingyo Zen Gain Weight & Color Enhancer merupakan pakan premium dari JPD yang di import oleh PT Platinum Adi Sentosa. Pakan ini mengandung beta glucan dan ragi toruta yang berfungsi menjaga daya tahan tubuh goldfish dan juga mempercepaat pertumbuhannya.'
        ]);

        $productBrand = ProductBrand::create([
            'name' => 'Goldfish',
            'description' => 'Pakan Ikan merk Goldfish'
        ]);

        $product = Product::create([
            'name' => 'Goldfish',
            'description' => 'Pakan Ikan merk Goldfish',
            'product_category_id' => $productCategory->id,
            'product_brand_id' => $productBrand->id
        ]);

        $productUnit = ProductUnit::create([
            'name' => 'Goldfish Merah',
            'product_id' => $product->id,
            'price' => '25000',
            'description' => 'Pakan Ikan merk Goldfish Merah',
        ]);

        $productUnit = ProductUnit::create([
            'name' => 'Goldfish Biru',
            'product_id' => $product->id,
            'price' => '100000',
            'description' => 'Pakan Ikan merk Goldfish Biru',
        ]);
    }
}
