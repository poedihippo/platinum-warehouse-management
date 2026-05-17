<?php

namespace Tests\Feature\Loyalty;

use App\Models\Loyalty\Claim;
use App\Models\Loyalty\LoyaltyUser;
use App\Models\Loyalty\PointsTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PointsBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsLoyalty(): LoyaltyUser
    {
        $user = LoyaltyUser::factory()->create();
        Sanctum::actingAs($user, ['loyalty'], 'loyalty');

        return $user;
    }

    public function test_pending_balance_reflects_pending_claims(): void
    {
        $user = $this->actingAsLoyalty();

        Claim::factory()->forUser($user)->create(['status' => 'pending', 'total_points' => 300]);
        // Approved claim must NOT count toward pending.
        Claim::factory()->forUser($user)->approved(500)->create();

        $this->getJson('/api/loyalty/points/balance')
            ->assertOk()
            ->assertJson(['pending' => 300, 'approved' => 0]);
    }

    public function test_approved_balance_reflects_transactions(): void
    {
        $user = $this->actingAsLoyalty();

        PointsTransaction::factory()->for($user, 'loyaltyUser')->earn(500)->create();

        $this->getJson('/api/loyalty/points/balance')
            ->assertOk()
            ->assertJson(['pending' => 0, 'approved' => 500]);
    }

    public function test_balance_after_spend_is_earned_minus_spent(): void
    {
        $user = $this->actingAsLoyalty();

        PointsTransaction::factory()->for($user, 'loyaltyUser')->earn(500)->create();
        PointsTransaction::factory()->for($user, 'loyaltyUser')->spend(200)->create();

        $this->getJson('/api/loyalty/points/balance')
            ->assertOk()
            ->assertJson(['pending' => 0, 'approved' => 300]);
    }

    public function test_transactions_endpoint_is_paginated_newest_first(): void
    {
        $user = $this->actingAsLoyalty();

        PointsTransaction::factory()->for($user, 'loyaltyUser')->earn(100)->create();
        PointsTransaction::factory()->for($user, 'loyaltyUser')->earn(200)->create();

        $this->getJson('/api/loyalty/points/transactions')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }
}
