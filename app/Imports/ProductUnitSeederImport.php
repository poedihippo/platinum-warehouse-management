<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Uom;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ProductUnitSeederImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // dd($row);
        $productUnitName = trim($row['product_unit_name']);
        $productName = trim($row['product_name']);
        $uom = trim($row['uom_name']);
        // $isGenerateQr = (int) trim($row['is_generate_qr'] ?? 1);
        $price = isset($row['price']) && !empty($row['price']) ? ((int) trim($row['price'])) : 0;
        $code = trim($row['code']);

        $product = Product::select('id')->firstWhere('name', $productName);
        if (!$product) {
            throw new UnprocessableEntityHttpException('Product not found');
        }

        $uom = Uom::select('id')->firstWhere('name', $uom);
        if (!$uom) {
            throw new UnprocessableEntityHttpException('Product category not found');
        }

        if (ProductUnit::where('name', $productUnitName)->doesntExist() || ProductUnit::where('code', $code)->doesntExist()) {
            return new ProductUnit([
                'product_id' => $product->id,
                'uom_id' => $uom->id,
                'code' => $code,
                'name' => $productUnitName,
                'description' => $productUnitName,
                'is_generate_qr' => 1,
                'price' => $price,
            ]);
        }
    }
}
