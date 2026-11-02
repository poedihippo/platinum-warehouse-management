<?php

namespace App\Imports;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;

class ProductCategorySeederImport implements ToModel
{
    /**
     * @return Model|null
     */
    public function model(array $row): Model|array|null
    {
        $productCategoryName = trim($row[0]);
        if (ProductCategory::where('name', $productCategoryName)->exists()) {
            return null;
        }

        return new ProductCategory([
            'name' => $productCategoryName,
            // 'description' => $productCategoryName,
        ]);
    }
}
