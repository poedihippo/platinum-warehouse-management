<?php

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\Prize;
use App\Models\Loyalty\Redemption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loyalty\Redemption>
 */
class RedemptionFactory extends Factory
{
    protected $model = Redemption::class;

    public function definition(): array
    {
        return [
            'loyalty_user_id' => LoyaltyUserFactory::new(),
            'prize_id' => PrizeFactory::new(),
            'points_spent' => fake()->numberBetween(100, 2000),
            'quantity' => 1,
            'status' => Redemption::STATUS_PENDING,
            'recipient_name' => fake()->name(),
            'recipient_phone' => fake()->numerify('08##########'),
            'recipient_address' => fake()->address(),
            'recipient_notes' => null,
            'submitted_at' => now(),
        ];
    }

    public function forPrize(Prize $prize): static
    {
        return $this->state(fn () => [
            'prize_id' => $prize->id,
            'points_spent' => $prize->points_cost,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => Redemption::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn () => [
            'status' => Redemption::STATUS_SHIPPED,
            'reviewed_at' => now(),
            'tracking_number' => 'JNE-' . fake()->numerify('##########'),
            'shipping_carrier' => 'JNE',
            'shipped_at' => now(),
        ]);
    }
}
