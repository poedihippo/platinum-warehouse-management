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

        $prodcutCategoryId = ProductCategory::select('id')->firstWhere('name', $categoryName);
        if (!$prodcutCategoryId) {
            throw new UnprocessableEntityHttpException('Product category not found');
        }

        $prodcutbrandId = ProductBrand::select('id')->firstWhere('name', $brandName);
        if (!$prodcutbrandId) {
            throw new UnprocessableEntityHttpException('Product category not found');
        }

        if (Product::where('name', $productName)->doesntExist()) {
            return new Product([
                'product_category_id' => $prodcutCategoryId,
                'product_brand_id' => $prodcutbrandId,
                'company' => $company,
                'name' => $productName,
                'description' => $productName,
            ]);
        }
    }
}
