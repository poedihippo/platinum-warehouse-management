<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductUnit;
use Maatwebsite\Excel\Concerns\ToModel;

class ProductUnitSeederImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $productUnitName = trim($row[2]);
        if (ProductUnit::where('name', $productUnitName)->exists()) return;

        return new ProductUnit([
            'product_id' => Product::where('name', $productUnitName)->first()?->id ?? 1,
            'uom_id' => 1,
            'code' => $row[0],
            'name' => $row[1],
            'description' => $row[1],
            'price' => 0,
        ]);
    }
}
