<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ProductSeederImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $categoryName = trim($row['category_name']);
        $company = trim($row['company']);
        $brandName = trim($row['brand_name']);
        $productName = trim($row['product_name']);

        $prodcutCategory = ProductCategory::select('id')->firstWhere('name', $categoryName);
        if (!$prodcutCategory) {
            throw new UnprocessableEntityHttpException('Product category not found');
        }

        $prodcutbrand = ProductBrand::select('id')->firstWhere('name', $brandName);
        if (!$prodcutbrand) {
            throw new UnprocessableEntityHttpException('Product category not found');
        }

        if (Product::where('name', $productName)->doesntExist()) {
            return new Product([
                'product_category_id' => $prodcutCategory->id,
                'product_brand_id' => $prodcutbrand->id,
                'company' => $company,
                'name' => $productName,
                'description' => $productName,
            ]);
        }
    }
}
