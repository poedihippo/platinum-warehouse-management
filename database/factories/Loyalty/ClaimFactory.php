<?php

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\Claim;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loyalty\Claim>
 */
class ClaimFactory extends Factory
{
    protected $model = Claim::class;

    public function definition(): array
    {
        return [
            'loyalty_user_id' => LoyaltyUserFactory::new(),
            'invoice_number' => 'INV-' . fake()->unique()->numerify('####-##-####'),
            'invoice_photo_path' => 'loyalty/claims/test/invoice.jpg',
            'status' => 'pending',
            'submitted_at' => now(),
            'total_points' => 0,
        ];
    }

    public function forUser(LoyaltyUser $user): static
    {
        return $this->state(fn () => ['loyalty_user_id' => $user->getKey()]);
    }

    public function approved(int $totalPoints = 0): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'reviewed_at' => now(),
            'total_points' => $totalPoints,
        ]);
    }
}
