<?php

namespace Database\Factories;

use App\Models\StockProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockProductUnit>
 */
class StockProductUnitFactory extends Factory
{
    protected $model = StockProductUnit::class;

    public function definition(): array
    {
        return [
            'product_unit_id' => ProductUnitFactory::new(),
            'warehouse_id'    => WarehouseFactory::new(),
            'qty'             => 0,
        ];
    }
}
