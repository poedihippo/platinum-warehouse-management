<?php

namespace Tests\Feature\Loyalty;

use App\Mail\Loyalty\RedemptionApprovedMail;
use App\Mail\Loyalty\RedemptionRejectedMail;
use App\Mail\Loyalty\RedemptionShippedMail;
use App\Models\Loyalty\LoyaltyUser;
use App\Models\Loyalty\PointsTransaction;
use App\Models\Loyalty\Prize;
use App\Models\Loyalty\Redemption;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RedemptionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prize photo_url accessor reads the s3 disk; fake it so no real
        // network/credentials are touched.
        Storage::fake('s3');
        Mail::fake();

        Permission::firstOrCreate(['name' => 'review redemptions', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage prizes', 'guard_name' => 'web']);
    }

    private function actingAsLoyalty(?LoyaltyUser $user = null): LoyaltyUser
    {
        $user ??= LoyaltyUser::factory()->create();
        Sanctum::actingAs($user, ['loyalty'], 'loyalty');

        return $user;
    }

    private function actingAsAdmin(bool $withReviewPermission = true): User
    {
        $admin = User::factory()->create();
        if ($withReviewPermission) {
            $admin->givePermissionTo('review redemptions');
        }
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function givePoints(LoyaltyUser $user, int $amount): void
    {
        PointsTransaction::factory()->earn($amount)->create([
            'loyalty_user_id' => $user->getKey(),
        ]);
    }

    private function redeemPayload(Prize $prize): array
    {
        return [
            'prize_id' => $prize->id,
            'recipient_name' => 'Budi',
            'recipient_phone' => '08123456789',
            'recipient_address' => 'Jl. Mawar No. 1, Surabaya',
        ];
    }

    // 1.
    public function test_customer_can_redeem_with_sufficient_points_and_stock(): void
    {
        $user = $this->actingAsLoyalty();
        $this->givePoints($user, 2000);
        $prize = Prize::factory()->create(['points_cost' => 1200, 'stock' => 5]);

        $this->postJson('/api/loyalty/redemptions', $this->redeemPayload($prize))
            ->assertCreated()
            ->assertJsonPath('data.points_spent', 1200)
            ->assertJsonPath('data.status', Redemption::STATUS_PENDING);

        $this->assertDatabaseHas('prizes', ['id' => $prize->id, 'stock' => 4]);
        $this->assertDatabaseHas('points_transactions', [
            'loyalty_user_id' => $user->getKey(),
            'direction' => PointsTransaction::DIRECTION_SPEND,
            'amount' => 1200,
            'source_type' => PointsTransaction::SOURCE_REDEMPTION,
        ]);
    }

    // 2.
    public function test_redeem_fails_with_insufficient_points(): void
    {
        $user = $this->actingAsLoyalty();
        $this->givePoints($user, 500);
        $prize = Prize::factory()->create(['points_cost' => 1200, 'stock' => 5]);

        $this->postJson('/api/loyalty/redemptions', $this->redeemPayload($prize))
            ->assertStatus(422)
            ->assertJson(['message' => 'Poin tidak cukup.']);

        $this->assertDatabaseHas('prizes', ['id' => $prize->id, 'stock' => 5]);
        $this->assertDatabaseMissing('redemptions', ['prize_id' => $prize->id]);
    }

    // 3.
    public function test_redeem_fails_when_out_of_stock(): void
    {
        $user = $this->actingAsLoyalty();
        $this->givePoints($user, 5000);
        $prize = Prize::factory()->outOfStock()->create(['points_cost' => 1200]);

        $this->postJson('/api/loyalty/redemptions', $this->redeemPayload($prize))
            ->assertStatus(422)
            ->assertJson(['message' => 'Hadiah sudah habis.']);

        $this->assertDatabaseMissing('redemptions', ['prize_id' => $prize->id]);
    }

    // 4.
    public function test_redeem_fails_on_inactive_prize(): void
    {
        $user = $this->actingAsLoyalty();
        $this->givePoints($user, 5000);
        $prize = Prize::factory()->inactive()->create(['points_cost' => 1200, 'stock' => 5]);

        $this->postJson('/api/loyalty/redemptions', $this->redeemPayload($prize))
            ->assertStatus(404);

        $this->assertDatabaseMissing('redemptions', ['prize_id' => $prize->id]);
    }

    // 5.
    public function test_admin_approve_changes_status_and_sends_email(): void
    {
        $redemption = Redemption::factory()->create();
        $this->actingAsAdmin();

        $this->postJson("/api/admin/loyalty/redemptions/{$redemption->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', Redemption::STATUS_APPROVED);

        $this->assertDatabaseHas('redemptions', [
            'id' => $redemption->id,
            'status' => Redemption::STATUS_APPROVED,
        ]);
        Mail::assertSent(RedemptionApprovedMail::class);
    }

    // 6.
    public function test_admin_reject_restores_stock_refunds_points_and_emails(): void
    {
        $user = LoyaltyUser::factory()->create();
        $prize = Prize::factory()->create(['points_cost' => 1200, 'stock' => 4]);
        $redemption = Redemption::factory()->forPrize($prize)->create([
            'loyalty_user_id' => $user->getKey(),
        ]);

        $this->actingAsAdmin();

        $this->postJson("/api/admin/loyalty/redemptions/{$redemption->id}/reject", [
            'reason' => 'Stok fisik habis.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Redemption::STATUS_REJECTED);

        // Stock restored.
        $this->assertDatabaseHas('prizes', ['id' => $prize->id, 'stock' => 5]);
        // Points refunded as an 'earn' ledger row.
        $this->assertDatabaseHas('points_transactions', [
            'loyalty_user_id' => $user->getKey(),
            'direction' => PointsTransaction::DIRECTION_EARN,
            'amount' => 1200,
            'source_type' => PointsTransaction::SOURCE_REDEMPTION,
            'source_id' => $redemption->id,
        ]);
        Mail::assertSent(RedemptionRejectedMail::class);
    }

    // 7.
    public function test_admin_ship_persists_tracking_and_emails(): void
    {
        $redemption = Redemption::factory()->approved()->create();
        $this->actingAsAdmin();

        $this->postJson("/api/admin/loyalty/redemptions/{$redemption->id}/ship", [
            'tracking_number' => 'JNE-1234567890',
            'shipping_carrier' => 'JNE',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Redemption::STATUS_SHIPPED);

        $this->assertDatabaseHas('redemptions', [
            'id' => $redemption->id,
            'status' => Redemption::STATUS_SHIPPED,
            'tracking_number' => 'JNE-1234567890',
            'shipping_carrier' => 'JNE',
        ]);
        Mail::assertSent(RedemptionShippedMail::class);
    }

    // 8.
    public function test_admin_deliver_is_terminal_with_no_email(): void
    {
        $redemption = Redemption::factory()->shipped()->create();
        $this->actingAsAdmin();

        $this->postJson("/api/admin/loyalty/redemptions/{$redemption->id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', Redemption::STATUS_DELIVERED);

        $this->assertDatabaseHas('redemptions', [
            'id' => $redemption->id,
            'status' => Redemption::STATUS_DELIVERED,
        ]);
        Mail::assertNothingSent();
    }

    // 9.
    public function test_customer_cannot_view_another_customers_redemption(): void
    {
        $owner = LoyaltyUser::factory()->create();
        $redemption = Redemption::factory()->create(['loyalty_user_id' => $owner->getKey()]);

        $this->actingAsLoyalty(); // a different customer

        $this->getJson("/api/loyalty/redemptions/{$redemption->id}")
            ->assertStatus(403);
    }

    // 10.
    public function test_admin_endpoints_require_permission(): void
    {
        $redemption = Redemption::factory()->create();
        $this->actingAsAdmin(withReviewPermission: false);

        $this->getJson('/api/admin/loyalty/redemptions')->assertStatus(403);
        $this->postJson("/api/admin/loyalty/redemptions/{$redemption->id}/approve")
            ->assertStatus(403);
    }
}
