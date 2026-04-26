<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'code'         => strtoupper(fake()->unique()->bothify('WH-###')),
            'name'         => fake()->company() . ' Warehouse',
            'company_name' => fake()->company(),
        ];
    }
}
