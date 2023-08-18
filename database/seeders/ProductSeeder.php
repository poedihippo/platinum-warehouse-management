<?php

namespace Database\Seeders;

use App\Imports\ProductUnitSeederImport;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductBrand;
use App\Models\ProductUnit;
use Maatwebsite\Excel\Facades\Excel;

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
            'name' => 'Mizuho',
            'description' => 'Pakan ikan air tawar merupakan pakan premium dari JPD yang di import oleh PT Platinum Adi Sentosa. Pakan ini mengandung beta glucan dan ragi toruta yang berfungsi menjaga daya tahan tubuh goldfish dan juga mempercepaat pertumbuhannya.'
        ]);

        $productBrandMizuho = ProductBrand::create([
            'name' => 'Mizuho',
            'description' => 'Pakan Ikan merk Mizuho'
        ]);

        $product = Product::create([
            'name' => 'Mizuho',
            'description' => 'Product Mizuho',
            'product_category_id' => $productCategory->id,
            'product_brand_id' => $productBrandMizuho->id
        ]);

        $productBrandJpd = ProductBrand::create([
            'name' => 'JPD',
            'description' => 'Pakan Ikan merk JPD'
        ]);

        Product::create([
            'name' => 'JPD',
            'description' => 'Product JPD',
            'product_category_id' => $productCategory->id,
            'product_brand_id' => $productBrandJpd->id
        ]);

        // ProductUnit::create([
        //     'uom_id' => 1,
        //     'code' => 'MIGF06',
        //     'name' => 'Mizuho GF Sinking @ 20kg',
        //     'product_id' => $product->id,
        //     'price' => '1000000',
        //     'description' => 'Mizuho GF Sinking @ 20kg',
        // ]);

        // ProductUnit::create([
        //     'uom_id' => 1,
        //     'code' => 'MISS01',
        //     'name' => 'Mizuho Sinking S @ 20kg',
        //     'product_id' => $product->id,
        //     'price' => '1193100',
        //     'description' => 'Mizuho Sinking S @ 20kg',
        // ]);

        // ProductUnit::create([
        //     'uom_id' => 1,
        //     'code' => 'MISM01',
        //     'name' => 'Mizuho Sinking M @ 20kg',
        //     'product_id' => $product->id,
        //     'price' => '1193100',
        //     'description' => 'Mizuho Sinking M @ 20kg',
        // ]);

        // ProductUnit::create([
        //     'uom_id' => 1,
        //     'code' => 'MISL01',
        //     'name' => 'Mizuho Sinking L @ 20kg',
        //     'product_id' => $product->id,
        //     'price' => '1193100',
        //     'description' => 'Mizuho Sinking L @ 20kg',
        // ]);

        ProductCategory::insert([
            [
                'name' => 'Matala',
                'description' => 'Matala',
            ],
            [
                'name' => 'JPD Food',
                'description' => 'JPD Food',
            ],
            [
                'name' => 'HI Silk Koi Food',
                'description' => 'HI Silk Koi Food',
            ],
            [
                'name' => 'Spare Part',
                'description' => 'Spare Part',
            ],
            [
                'name' => 'Filter Media',
                'description' => 'Filter Media',
            ],
            [
                'name' => 'Love Larva',
                'description' => 'Love Larva',
            ],
            [
                'name' => 'Box kemasan',
                'description' => 'Box kemasan',
            ],
            [
                'name' => 'Plastik Packing',
                'description' => 'Plastik Packing',
            ],
            [
                'name' => 'Alat-Alat',
                'description' => 'Alat-Alat',
            ],
            [
                'name' => 'Lain-lain',
                'description' => 'Lain-lain',
            ],
        ]);

        Excel::import(new ProductUnitSeederImport, public_path('product_units.xlsx'));
    }
}
