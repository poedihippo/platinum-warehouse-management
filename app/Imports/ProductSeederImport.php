<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductSeederImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $categoryName = trim($row['category_name']);
        $brandName = trim($row['brand_name']);
        $productName = trim($row['product_name']);

        if (Product::where('name', $productName)->doesntExist()) {
            return new Product([
                'product_category_id' => ProductCategory::where('name', $categoryName)->first()?->id ?? 1,
                'product_brand_id' => ProductBrand::where('name', $brandName)->first()?->id ?? 1,
                'name' => $productName,
                'description' => $productName,
            ]);
        }

        return;
    }
}
