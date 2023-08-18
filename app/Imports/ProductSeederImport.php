<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use Maatwebsite\Excel\Concerns\ToModel;

class ProductSeederImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $categoryName = trim($row[0]);
        $brandName = trim($row[1]);
        return new Product([
            'product_category_id' => ProductCategory::where('name', $categoryName)->first()?->id ?? 1,
            'product_brand_id' => ProductBrand::where('name', $brandName)->first()?->id ?? 1,
            'name' => $row[2],
            'description' => $row[2],
        ]);
    }
}
