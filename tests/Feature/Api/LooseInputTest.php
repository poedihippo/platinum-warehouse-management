<?php

namespace Tests\Feature\Api;

use App\Models\Loyalty\Claim;
use App\Models\Loyalty\LoyaltyUser;
use App\Models\Loyalty\Prize;
use App\Models\Loyalty\Redemption;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class LooseInputTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    public function test_stock_show_with_empty_include_still_answers_200(): void
    {
        $this->actingAsAdmin();
        $group = $this->makeGroupedStock($this->spu($this->makeProductUnit(true), $this->makeWarehouse()), 2);

        $this->getJson("/api/stocks/{$group->id}?include=")->assertOk()->assertJsonPath('data.id', $group->id);
    }

    public function test_loyalty_admin_indexes_accept_array_query_params(): void
    {
        Storage::fake('s3');
        $admin = $this->makeUser();
        foreach (['review claims', 'review redemptions', 'manage customers', 'manage prizes'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $admin->givePermissionTo(['review claims', 'review redemptions', 'manage customers', 'manage prizes']);
        Sanctum::actingAs($admin);

        $customer = LoyaltyUser::factory()->create();
        Claim::factory()->forUser($customer)->create();
        Redemption::factory()->forPrize(Prize::factory()->create())->create(['loyalty_user_id' => $customer->id]);

        $this->getJson('/api/admin/loyalty/claims?status[]=pending&sort[]=x')->assertOk();
        $this->getJson('/api/admin/loyalty/redemptions?status[]=pending&sort[]=x')->assertOk();
        $this->getJson('/api/admin/loyalty/customers?sort[]=x')->assertOk();
        $this->getJson('/api/admin/loyalty/prizes?sort[]=x')->assertOk();
    }

    public function test_loyalty_customer_indexes_accept_array_query_params(): void
    {
        Storage::fake('s3');
        $customer = LoyaltyUser::factory()->create();
        Sanctum::actingAs($customer, ['loyalty'], 'loyalty');
        Prize::factory()->create();

        $this->getJson('/api/loyalty/claims?status[]=pending')->assertOk();
        $this->getJson('/api/loyalty/redemptions?status[]=pending')->assertOk();
        $this->getJson('/api/loyalty/prizes?sort[]=x&prize_category_id[]=1')->assertOk();
    }

    public function test_role_and_blacklist_writes_tolerate_loose_ids(): void
    {
        $this->actingAsAdmin();
        $permission = Permission::firstOrCreate(['name' => 'uom_read', 'guard_name' => 'web']);

        $this->postJson('/api/roles', ['name' => 'loose-role', 'permission_ids' => [null, $permission->id]])->assertCreated();
        $this->assertTrue(Role::where('name', 'loose-role')->firstOrFail()->hasPermissionTo('uom_read'));

        $role = Role::firstWhere('name', 'loose-role');
        $this->putJson("/api/roles/{$role->id}", ['name' => 'loose-role', 'permission_ids' => [null]])->assertOk();

        $unit = $this->makeProductUnit(false);
        $this->postJson('/api/product-unit-blacklists', ['product_unit_ids' => [$unit->id]])->assertOk();
        $this->postJson('/api/product-unit-blacklists', ['product_unit_ids' => [[$unit->id]]])
            ->assertOk()->assertExactJson(['message' => '0 inserted successfully']);
    }
}
