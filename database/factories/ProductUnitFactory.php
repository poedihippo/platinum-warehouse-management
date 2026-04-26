<?php

namespace Database\Factories;

use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductUnit>
 */
class ProductUnitFactory extends Factory
{
    protected $model = ProductUnit::class;

    public function definition(): array
    {
        return [
            'product_id'      => ProductFactory::new(),
            'uom_id'          => UomFactory::new(),
            'name'            => fake()->words(2, true),
            'price'           => fake()->numberBetween(1000, 500000),
            'refer_qty'       => null,
            'code'            => strtoupper(fake()->unique()->bothify('PU-####')),
            'is_generate_qr'  => true,
            'is_ppn'          => false,
        ];
    }
}
