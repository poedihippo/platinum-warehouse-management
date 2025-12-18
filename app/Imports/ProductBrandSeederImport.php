<?php

namespace App\Imports;

use App\Models\ProductBrand;
use Maatwebsite\Excel\Concerns\ToModel;

class ProductBrandSeederImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $productBrandName = trim($row[0]);
        if (ProductBrand::where('name', $productBrandName)->exists()) return;

        return new ProductBrand([
            'name' => $productBrandName,
            // 'description' => $productBrandName,
        ]);
    }
}
