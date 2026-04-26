<?php

namespace Database\Factories;

use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Uom>
 */
class UomFactory extends Factory
{
    protected $model = Uom::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['PCS', 'BOX', 'KG', 'PACK']),
        ];
    }
}
