<?php

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loyalty\LoyaltyUser>
 */
class LoyaltyUserFactory extends Factory
{
    protected $model = LoyaltyUser::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            // Hashed by the model's password mutator on assignment.
            'password' => 'password',
            'email_verified_at' => now(),
            'phone' => fake()->numerify('08##########'),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
