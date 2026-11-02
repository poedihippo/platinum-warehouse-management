<?php

namespace Tests\Feature\Loyalty;

use App\Models\Loyalty\Claim;
use App\Models\Loyalty\LoyaltyUser;
use App\Models\Loyalty\PointsTransaction;
use App\Models\Loyalty\Prize;
use App\Models\Loyalty\PrizeCategory;
use App\Models\Loyalty\Redemption;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class LoyaltyCoverageTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.default' => 'local']);
        Storage::fake('local');
        Storage::fake('s3');
        Mail::fake();
    }

    private function customer(array $attributes = []): LoyaltyUser
    {
        $user = LoyaltyUser::factory()->create($attributes);
        Sanctum::actingAs($user, ['loyalty'], 'loyalty');

        return $user;
    }

    private function admin(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $admin = $this->makeUser();
        $admin->givePermissionTo($permissions);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function earn(LoyaltyUser $user, int $amount): void
    {
        PointsTransaction::factory()->earn($amount)->create(['loyalty_user_id' => $user->id]);
    }

    public function test_login_logout_me_and_profile(): void
    {
        $user = LoyaltyUser::factory()->create(['email' => 'c@test.com', 'password' => 'secret']);

        $this->postJson('/api/loyalty/auth/login', ['email' => 'c@test.com', 'password' => 'bad'])
            ->assertStatus(422)->assertExactJson(['message' => 'Email atau kata sandi salah.']);

        $login = $this->postJson('/api/loyalty/auth/login', ['email' => 'c@test.com', 'password' => 'secret'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('data.email', 'c@test.com');
        $this->assertSame(['loyalty'], $user->tokens()->first()->abilities);

        $user->forceFill(['is_active' => false])->save();
        $this->postJson('/api/loyalty/auth/login', ['email' => 'c@test.com', 'password' => 'secret'])
            ->assertForbidden()->assertExactJson(['message' => 'Akun dinonaktifkan.']);
        $user->forceFill(['is_active' => true])->save();

        $this->customer()->forceFill(['address' => 'Old'])->save();
        $this->getJson('/api/loyalty/me')->assertOk()->assertJsonStructure(['data' => ['id', 'email']]);
        $this->getJson('/api/loyalty/profile')->assertOk()->assertJsonPath('data.address', 'Old');
        $this->patchJson('/api/loyalty/profile', ['address' => 'New', 'phone' => '0811'])
            ->assertOk()->assertJsonPath('data.address', 'New');

        $this->withHeader('Authorization', 'Bearer '.$login->json('token'));
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/loyalty/auth/logout')->assertOk()->assertExactJson(['message' => 'Berhasil keluar.']);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_verify_email_and_password_reset(): void
    {
        $user = LoyaltyUser::factory()->unverified()->create(['email' => 'v@test.com']);

        $this->postJson('/api/loyalty/auth/password-reset/request', ['email' => 'nobody@test.com'])
            ->assertOk()->assertExactJson(['message' => 'Jika email terdaftar, tautan reset kata sandi telah dikirim.']);
        $this->postJson('/api/loyalty/auth/password-reset/request', ['email' => 'v@test.com'])->assertOk();
        Mail::assertSent(\App\Mail\Loyalty\PasswordResetMail::class, 1);

        $this->postJson('/api/loyalty/auth/password-reset/confirm', [
            'email' => 'v@test.com', 'token' => 'wrong', 'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertStatus(422)->assertExactJson(['message' => 'Token reset tidak valid atau kedaluwarsa.']);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_customer_claims_index_show_and_ownership(): void
    {
        $user = $this->customer();
        $other = LoyaltyUser::factory()->create();
        $mine = Claim::factory()->forUser($user)->create(['status' => 'pending', 'submitted_at' => now()->subDay()]);
        Claim::factory()->forUser($user)->approved(100)->create(['submitted_at' => now()]);
        $foreign = Claim::factory()->forUser($other)->create();

        $this->getJson('/api/loyalty/claims?status=pending')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson('/api/loyalty/claims?per_page=500')->assertOk()->assertJsonPath('meta.per_page', 15)->assertJsonCount(2, 'data');
        $this->getJson("/api/loyalty/claims/{$mine->id}")->assertOk()->assertJsonPath('data.id', $mine->id);
        $this->getJson("/api/loyalty/claims/{$foreign->id}")->assertNotFound()->assertExactJson(['message' => 'Klaim tidak ditemukan.']);
    }

    public function test_prize_catalog_filters_sort_and_affordable(): void
    {
        $user = $this->customer();
        $this->earn($user, 500);
        $category = PrizeCategory::create(['name' => 'Elektronik', 'is_active' => true]);
        $inactiveCategory = PrizeCategory::create(['name' => 'Lama', 'is_active' => false]);
        $cheap = Prize::factory()->create(['name' => 'Kaos', 'points_cost' => 100, 'prize_category_id' => $category->id]);
        $pricey = Prize::factory()->create(['name' => 'Radio', 'points_cost' => 900, 'prize_category_id' => $category->id]);
        $orphan = Prize::factory()->create(['name' => 'Topi', 'points_cost' => 300, 'prize_category_id' => $inactiveCategory->id]);
        $inactive = Prize::factory()->inactive()->create(['name' => 'Hidden']);

        $this->getJson('/api/loyalty/prizes')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.id', $cheap->id);
        $this->getJson('/api/loyalty/prizes?sort=points_desc')->assertOk()->assertJsonPath('data.0.id', $pricey->id);
        $this->getJson('/api/loyalty/prizes?q=adi')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/loyalty/prizes?prize_category_id={$category->id}")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/loyalty/prizes?prize_category_id=none')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $orphan->id);
        $this->getJson('/api/loyalty/prizes?affordable=1&sort=name_asc')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $cheap->id);
        $this->getJson("/api/loyalty/prizes/{$inactive->id}")->assertNotFound()->assertExactJson(['message' => 'Hadiah tidak ditemukan.']);
        $this->getJson("/api/loyalty/prizes/{$cheap->id}")->assertOk()->assertJsonPath('data.name', 'Kaos');
        $this->getJson('/api/loyalty/prize-categories')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Elektronik');
    }

    public function test_customer_redemption_index_and_show(): void
    {
        $user = $this->customer();
        $prize = Prize::factory()->create();
        $redemption = Redemption::factory()->forPrize($prize)->create(['loyalty_user_id' => $user->id]);

        $this->getJson('/api/loyalty/redemptions?status=pending')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $redemption->id);
        $this->getJson('/api/loyalty/redemptions/unknown')->assertNotFound()->assertExactJson(['message' => 'Penukaran tidak ditemukan.']);
        $this->postJson('/api/loyalty/redemptions/unknown/deliver')->assertNotFound();
    }

    public function test_redeem_twice_is_a_conflict(): void
    {
        $user = $this->customer();
        $this->earn($user, 5000);
        $prize = Prize::factory()->create(['points_cost' => 100, 'stock' => 5]);
        $payload = ['prize_id' => $prize->id, 'recipient_name' => 'A', 'recipient_phone' => '08', 'recipient_address' => 'Jl. X'];

        $this->postJson('/api/loyalty/redemptions', $payload)->assertStatus(201);
        $this->assertSame('Jl. X', $user->fresh()->address);
        $this->postJson('/api/loyalty/redemptions', $payload)
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Anda masih memiliki penukaran hadiah ini yang sedang diproses.']);
        $this->assertSame(4, $prize->fresh()->stock);
    }

    public function test_claim_review_index_show_line_items_and_reject(): void
    {
        $customer = LoyaltyUser::factory()->create(['name' => 'Siti']);
        $claim = Claim::factory()->forUser($customer)->create(['invoice_number' => 'INV-77']);
        Claim::factory()->forUser(LoyaltyUser::factory()->create())->create(['invoice_number' => 'INV-77']);
        $unit = $this->makeProductUnit();
        $unit->forceFill(['points_per_unit' => 10])->save();
        $zeroUnit = $this->makeProductUnit();
        $this->admin(['review claims']);

        $this->getJson('/api/admin/loyalty/claims?q=Siti&status=all&sort=invoice_number_asc')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $claim->id);
        $this->getJson("/api/admin/loyalty/claims/{$claim->id}")->assertOk()->assertJsonCount(1, 'data.duplicate_warnings');
        $this->getJson('/api/admin/loyalty/claims/unknown')->assertNotFound()->assertExactJson(['message' => 'Klaim tidak ditemukan.']);

        $this->postJson("/api/admin/loyalty/claims/{$claim->id}/line-items", ['product_unit_id' => $zeroUnit->id, 'quantity' => 1])
            ->assertStatus(422)->assertExactJson(['message' => 'Product unit ini tidak memiliki nilai poin (points_per_unit harus > 0).']);
        $item = $this->postJson("/api/admin/loyalty/claims/{$claim->id}/line-items", ['product_unit_id' => $unit->id, 'quantity' => 2])
            ->assertStatus(201)->assertJsonPath('message', 'Line item ditambahkan.')->assertJsonPath('data.quantity', 2);
        $itemId = $item->json('data.id');

        $this->patchJson("/api/admin/loyalty/claims/{$claim->id}/line-items/{$itemId}", ['quantity' => 3])
            ->assertOk()->assertJsonPath('data.points_awarded', 30);
        $this->patchJson("/api/admin/loyalty/claims/{$claim->id}/line-items/unknown", ['quantity' => 3])
            ->assertNotFound()->assertExactJson(['message' => 'Line item tidak ditemukan.']);
        $this->deleteJson("/api/admin/loyalty/claims/{$claim->id}/line-items/{$itemId}")
            ->assertOk()->assertExactJson(['message' => 'Line item dihapus.']);

        $this->postJson("/api/admin/loyalty/claims/{$claim->id}/approve")
            ->assertStatus(422)->assertExactJson(['message' => 'Tidak dapat menyetujui klaim tanpa line item.']);

        $this->postJson("/api/admin/loyalty/claims/{$claim->id}/reject", ['reason' => 'Buram'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        Mail::assertSent(\App\Mail\Loyalty\ClaimRejectedMail::class);

        $this->postJson("/api/admin/loyalty/claims/{$claim->id}/reject", ['reason' => 'Lagi'])
            ->assertStatus(409)->assertExactJson(['message' => 'Klaim sudah direview.', 'status' => 'rejected']);
        $this->postJson("/api/admin/loyalty/claims/{$claim->id}/line-items", ['product_unit_id' => $unit->id, 'quantity' => 1])
            ->assertStatus(422)->assertExactJson(['message' => 'Klaim tidak lagi berstatus pending.', 'status' => 'rejected']);
    }

    public function test_claim_approve_awards_points(): void
    {
        $customer = LoyaltyUser::factory()->create();
        $claim = Claim::factory()->forUser($customer)->create();
        $unit = $this->makeProductUnit();
        $unit->forceFill(['points_per_unit' => 25])->save();
        $claim->lineItems()->create(['product_unit_id' => $unit->id, 'quantity' => 4, 'points_awarded' => 0]);
        $this->admin(['review claims']);

        $this->postJson("/api/admin/loyalty/claims/{$claim->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame(100, (int) $claim->fresh()->total_points);
        $this->assertSame(100, (int) PointsTransaction::where('loyalty_user_id', $customer->id)->where('direction', 'earn')->sum('amount'));
        $this->postJson("/api/admin/loyalty/claims/{$claim->id}/approve")->assertStatus(409);
    }

    public function test_redemption_review_index_show_and_conflict(): void
    {
        $customer = LoyaltyUser::factory()->create(['name' => 'Budi']);
        $prize = Prize::factory()->create(['name' => 'Payung']);
        $redemption = Redemption::factory()->forPrize($prize)->create(['loyalty_user_id' => $customer->id]);
        $this->admin(['review redemptions']);

        $this->getJson('/api/admin/loyalty/redemptions?q=Payung&status=pending')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/admin/loyalty/redemptions/{$redemption->id}")->assertOk()->assertJsonPath('data.id', $redemption->id);
        $this->getJson('/api/admin/loyalty/redemptions/unknown')->assertNotFound()->assertExactJson(['message' => 'Penukaran tidak ditemukan.']);
        $this->postJson("/api/admin/loyalty/redemptions/{$redemption->id}/ship", ['tracking_number' => 'T1', 'shipping_carrier' => 'JNE'])
            ->assertStatus(409)->assertExactJson(['message' => 'Status penukaran tidak memungkinkan aksi ini.', 'status' => 'pending']);
        $this->postJson('/api/admin/loyalty/redemptions/unknown/approve')->assertNotFound()->assertJsonPath('message', 'Penukaran tidak ditemukan.');
    }

    public function test_prize_management_index_toggle_and_categories(): void
    {
        $this->admin(['manage prizes']);
        $prize = Prize::factory()->create(['name' => 'Gelas', 'points_cost' => 10]);
        Prize::factory()->inactive()->create(['name' => 'Piring', 'points_cost' => 20]);

        $this->getJson('/api/admin/loyalty/prizes?is_active=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/loyalty/prizes?sort=points_desc')->assertOk()->assertJsonPath('data.0.name', 'Piring');
        $this->patchJson("/api/admin/loyalty/prizes/{$prize->id}/toggle-active")->assertOk()->assertJsonPath('data.is_active', false);
        $this->patchJson('/api/admin/loyalty/prizes/unknown/toggle-active')->assertNotFound()->assertExactJson(['message' => 'Hadiah tidak ditemukan.']);

        $category = $this->postJson('/api/admin/loyalty/prize-categories', ['name' => 'Rumah Tangga'])->assertStatus(201)->json('data');
        $this->getJson('/api/admin/loyalty/prize-categories')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/admin/loyalty/prize-categories/{$category['id']}", ['name' => 'Dapur'])->assertOk()->assertJsonPath('data.name', 'Dapur');
        $this->deleteJson("/api/admin/loyalty/prize-categories/{$category['id']}")->assertNoContent();
        $this->deleteJson("/api/admin/loyalty/prize-categories/{$category['id']}")->assertNotFound()->assertExactJson(['message' => 'Kategori hadiah tidak ditemukan.']);
    }

    public function test_prize_category_admin_requires_permission_after_validation(): void
    {
        $this->admin([]);

        $this->postJson('/api/admin/loyalty/prize-categories', [])->assertStatus(422);
        $this->postJson('/api/admin/loyalty/prize-categories', ['name' => 'X'])
            ->assertForbidden()->assertExactJson(['message' => 'Anda tidak memiliki izin untuk mengelola kategori hadiah.']);
    }

    public function test_customer_management(): void
    {
        $customer = LoyaltyUser::factory()->create(['name' => 'Ani']);
        LoyaltyUser::factory()->create(['name' => 'Zul']);
        $this->earn($customer, 300);
        $admin = $this->admin(['manage customers']);

        $this->getJson('/api/admin/loyalty/customers?sort=points_desc')->assertOk()->assertJsonPath('data.0.id', $customer->id)->assertJsonPath('data.0.points_balance', 300);
        $this->getJson('/api/admin/loyalty/customers?q=Zul')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/admin/loyalty/customers/{$customer->id}")->assertOk()->assertJsonPath('data.points_balance', 300);
        $this->getJson('/api/admin/loyalty/customers/unknown')->assertNotFound()->assertExactJson(['message' => 'Pelanggan tidak ditemukan.']);

        $this->postJson('/api/admin/loyalty/customers/unknown/adjust-points', [])->assertNotFound();
        $this->postJson("/api/admin/loyalty/customers/{$customer->id}/adjust-points", ['points' => -400, 'reason' => 'x'])
            ->assertStatus(422)->assertExactJson(['message' => 'Saldo tidak cukup.']);
        $this->postJson("/api/admin/loyalty/customers/{$customer->id}/adjust-points", ['points' => -100, 'reason' => 'Koreksi'])
            ->assertOk()->assertJsonPath('data.points_balance', 200);

        $adjustment = PointsTransaction::where('source_type', 'manual_adjustment')->firstOrFail();
        $this->assertSame($adjustment->id, $adjustment->source_id);
        $this->assertSame($admin->id, (int) $adjustment->adjusted_by);

        $this->patchJson("/api/admin/loyalty/customers/{$customer->id}/toggle-active")->assertOk()->assertJsonPath('data.points_balance', 200);
        $this->assertFalse((bool) $customer->fresh()->is_active);
    }

    public function test_brand_list_and_admin_me(): void
    {
        \App\Models\ProductBrand::create(['name' => 'B Brand']);
        \App\Models\ProductBrand::create(['name' => 'A Brand']);
        $this->admin(['manage brands', 'review claims']);

        $this->getJson('/api/admin/loyalty/brands')->assertOk()->assertJsonPath('data.0.name', 'A Brand');
        $this->getJson('/api/admin/loyalty/me')
            ->assertOk()
            ->assertJsonPath('data.permissions', ['review claims', 'manage brands']);
    }
}
