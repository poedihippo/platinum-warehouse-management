<?php

namespace Tests\Feature\Loyalty;

use App\Mail\Loyalty\ClaimApprovedMail;
use App\Models\Loyalty\Claim;
use App\Models\Loyalty\LoyaltyUser;
use App\Models\Loyalty\PointsTransaction;
use App\Models\ProductUnit;
use App\Models\User;
use Database\Factories\ProductUnitFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function makeProductUnit(int $pointsPerUnit): ProductUnit
    {
        // ProductUnit has create-event side-effects (auto stock_product_units);
        // bypass them — this test owns exactly the rows it creates.
        return Model::withoutEvents(function () use ($pointsPerUnit) {
            $unit = ProductUnitFactory::new()->create();
            $unit->points_per_unit = $pointsPerUnit; // not fillable; set directly
            $unit->save();

            return $unit;
        });
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_approve_writes_points_transaction_and_total_and_emails(): void
    {
        Mail::fake();

        $customer = LoyaltyUser::factory()->create();
        $claim = Claim::factory()->forUser($customer)->create();
        $unit = $this->makeProductUnit(200);
        $claim->lineItems()->create([
            'product_unit_id' => $unit->id,
            'quantity' => 2,
            'points_awarded' => 0,
        ]);

        $this->actingAsAdmin();

        $this->postJson("/api/admin/loyalty/claims/{$claim->id}/approve")
            ->assertOk();

        $claim->refresh();
        $this->assertSame('approved', $claim->status);
        $this->assertSame(400, (int) $claim->total_points);
        $this->assertSame(400, (int) $claim->lineItems()->first()->points_awarded);

        $this->assertDatabaseHas('points_transactions', [
            'loyalty_user_id' => $customer->getKey(),
            'direction' => PointsTransaction::DIRECTION_EARN,
            'amount' => 400,
            'source_type' => PointsTransaction::SOURCE_CLAIM,
            'source_id' => $claim->id,
        ]);

        Mail::assertSent(ClaimApprovedMail::class);
    }

    public function test_cannot_approve_twice(): void
    {
        Mail::fake();

        $claim = Claim::factory()->create();
        $unit = $this->makeProductUnit(100);
        $claim->lineItems()->create([
            'product_unit_id' => $unit->id,
            'quantity' => 1,
            'points_awarded' => 0,
        ]);

        $this->actingAsAdmin();

        $this->postJson("/api/admin/loyalty/claims/{$claim->id}/approve")->assertOk();

        // Second approval is rejected with 409 Conflict.
        $this->postJson("/api/admin/loyalty/claims/{$claim->id}/approve")
            ->assertStatus(409);

        $this->assertSame(
            1,
            PointsTransaction::where('source_id', $claim->id)->count()
        );
    }

    public function test_cannot_approve_without_line_items(): void
    {
        $claim = Claim::factory()->create();
        $this->actingAsAdmin();

        $this->postJson("/api/admin/loyalty/claims/{$claim->id}/approve")
            ->assertStatus(422);

        $this->assertSame('pending', $claim->fresh()->status);
    }
}
