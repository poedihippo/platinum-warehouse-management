<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductUnit;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductUnitSeederImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $productUnitName = trim($row['name']);
        $productName = trim($row['product_name']);
        if (ProductUnit::where('name', $productUnitName)->exists())
            return;

        return new ProductUnit([
            'product_id' => Product::where('name', $productName)->first()?->id ?? 1,
            'uom_id' => 1,
            'code' => $row['code'],
            'name' => $productUnitName,
            'description' => $productUnitName,
            'price' => 0,
        ]);
    }
}
