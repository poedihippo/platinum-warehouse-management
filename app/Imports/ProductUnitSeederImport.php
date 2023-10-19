<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Uom;
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
        $productUnitName = trim($row['product_unit_name']);
        $productName = trim($row['product_name']);
        $uom = trim($row['uom_name']);
        $isGenerateQr = (int) trim($row['is_generate_qr']);

        if (ProductUnit::where('name', $productUnitName)->doesntExist()) {
            return new ProductUnit([
                'product_id' => Product::where('name', $productName)->first()?->id ?? 1,
                'uom_id' => Uom::where('name', $uom)->first()?->id ?? 1,
                'code' => trim($row['code']),
                'name' => $productUnitName,
                'description' => $productUnitName,
                'is_generate_qr' => $isGenerateQr,
                'price' => 0,
            ]);
        }

        return;
    }
}
