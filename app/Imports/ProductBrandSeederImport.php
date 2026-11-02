<?php

namespace App\Imports;

use App\Models\ProductBrand;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;

class ProductBrandSeederImport implements ToModel
{
    /**
     * @return Model|null
     */
    public function model(array $row): Model|array|null
    {
        $productBrandName = trim($row[0]);
        if (ProductBrand::where('name', $productBrandName)->exists()) {
            return null;
        }

        return new ProductBrand([
            'name' => $productBrandName,
            // 'description' => $productBrandName,
        ]);
    }
}
