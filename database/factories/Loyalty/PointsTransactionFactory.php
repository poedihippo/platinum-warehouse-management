<?php

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\PointsTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loyalty\PointsTransaction>
 */
class PointsTransactionFactory extends Factory
{
    protected $model = PointsTransaction::class;

    public function definition(): array
    {
        return [
            'loyalty_user_id' => LoyaltyUserFactory::new(),
            'direction' => PointsTransaction::DIRECTION_EARN,
            'amount' => fake()->numberBetween(50, 500),
            'source_type' => PointsTransaction::SOURCE_CLAIM,
            'source_id' => strtolower((string) Str::ulid()),
            'description' => 'Test transaction',
        ];
    }

    public function earn(int $amount): static
    {
        return $this->state(fn () => [
            'direction' => PointsTransaction::DIRECTION_EARN,
            'amount' => $amount,
        ]);
    }

    public function spend(int $amount): static
    {
        return $this->state(fn () => [
            'direction' => PointsTransaction::DIRECTION_SPEND,
            'amount' => $amount,
            'source_type' => PointsTransaction::SOURCE_REDEMPTION,
        ]);
    }
}
