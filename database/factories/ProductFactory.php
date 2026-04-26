<?php

namespace Database\Factories;

use App\Enums\CompanyEnum;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'product_category_id' => ProductCategoryFactory::new(),
            'product_brand_id'    => ProductBrandFactory::new(),
            'company'             => CompanyEnum::PAS,
            'name'                => fake()->words(3, true),
        ];
    }
}
