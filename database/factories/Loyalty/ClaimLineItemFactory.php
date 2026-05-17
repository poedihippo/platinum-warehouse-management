<?php

namespace Database\Factories\Loyalty;

use App\Models\Loyalty\ClaimLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loyalty\ClaimLineItem>
 */
class ClaimLineItemFactory extends Factory
{
    protected $model = ClaimLineItem::class;

    public function definition(): array
    {
        return [
            'claim_id' => ClaimFactory::new(),
            // product_unit_id is supplied explicitly by tests so the
            // ProductUnit model side-effects can be controlled.
            'product_unit_id' => null,
            'quantity' => 1,
            'points_awarded' => 0,
        ];
    }
}
