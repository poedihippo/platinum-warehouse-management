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
        $productUnitName = trim($row['product_unit_name']);
        $productName = trim($row['product_name']);
        $uom = trim($row['uom_name']);
        // $isGenerateQr = (int) trim($row['is_generate_qr'] ?? 1);
        $price = isset($row['price']) && !empty($row['price']) ? ((int) trim($row['price'])) : 0;

        $productId = Product::select('id')->firstWhere('name', $productName);
        if (!$productId) {
            throw new UnprocessableEntityHttpException('Product not found');
        }

        $uomId = Uom::select('id')->firstWhere('name', $uom);
        if (!$uomId) {
            throw new UnprocessableEntityHttpException('Product category not found');
        }

        if (ProductUnit::where('name', $productUnitName)->doesntExist()) {
            return new ProductUnit([
                'product_id' => $productId,
                'uom_id' => $uomId,
                'code' => trim($row['code']),
                'name' => $productUnitName,
                'description' => $productUnitName,
                'is_generate_qr' => 1,
                'price' => $price,
            ]);
        }
    }
}
