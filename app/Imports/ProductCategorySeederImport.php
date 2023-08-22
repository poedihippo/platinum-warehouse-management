<?php

namespace App\Imports;

use App\Models\ProductCategory;
use Maatwebsite\Excel\Concerns\ToModel;

class ProductCategorySeederImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $productCategoryName = trim($row[0]);
        if (ProductCategory::where('name', $productCategoryName)->exists()) return;

        return new ProductCategory([
            'name' => $productCategoryName,
            'description' => $productCategoryName,
        ]);
    }
}
