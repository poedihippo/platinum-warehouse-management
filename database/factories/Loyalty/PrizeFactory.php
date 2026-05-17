<?php

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\Prize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loyalty\Prize>
 */
class PrizeFactory extends Factory
{
    protected $model = Prize::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'photo_path' => 'loyalty/prizes/test.jpg',
            'point_cost' => fake()->numberBetween(100, 2000),
            'stock' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }
}
