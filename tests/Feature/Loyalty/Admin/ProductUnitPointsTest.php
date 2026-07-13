<?php

namespace Tests\Feature\Loyalty\Admin;

use App\Models\Permission;
use App\Models\ProductUnit;
use App\Models\User;
use Database\Factories\ProductUnitFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductUnitPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate([
            'name' => 'manage loyalty points',
            'guard_name' => 'web',
        ]);
    }

    private function makeProductUnit(): ProductUnit
    {
        // ProductUnit create events cascade into stock_product_units; this
        // test owns only the rows it needs, so bypass them.
        return Model::withoutEvents(fn () => ProductUnitFactory::new()->create());
    }

    private function actingAsAdmin(bool $withPermission): User
    {
        $admin = User::factory()->create(['type' => 'admin']);

        if ($withPermission) {
            $admin->givePermissionTo('manage loyalty points');
        }

        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_user_with_permission_can_update_points(): void
    {
        $this->actingAsAdmin(withPermission: true);
        $unit = $this->makeProductUnit();
        $unit->loyalty_eligible = true; // not fillable; set directly
        $unit->save();

        $this->patchJson("/api/admin/product-units/{$unit->id}/points", [
            'points_per_unit' => 100,
        ])
            ->assertOk()
            ->assertJsonPath('data.points_per_unit', 100);

        $this->assertDatabaseHas('product_units', [
            'id' => $unit->id,
            'points_per_unit' => 100,
        ]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->actingAsAdmin(withPermission: false);
        $unit = $this->makeProductUnit();

        $this->patchJson("/api/admin/product-units/{$unit->id}/points", [
            'points_per_unit' => 100,
        ])
            ->assertStatus(403)
            ->assertJson(['message' => 'You are not authorized to manage loyalty points.']);

        $this->assertDatabaseHas('product_units', [
            'id' => $unit->id,
            'points_per_unit' => 0,
        ]);
    }

    public function test_negative_value_is_rejected(): void
    {
        $this->actingAsAdmin(withPermission: true);
        $unit = $this->makeProductUnit();

        $this->patchJson("/api/admin/product-units/{$unit->id}/points", [
            'points_per_unit' => -1,
        ])->assertStatus(422);
    }

    public function test_non_integer_value_is_rejected(): void
    {
        $this->actingAsAdmin(withPermission: true);
        $unit = $this->makeProductUnit();

        $this->patchJson("/api/admin/product-units/{$unit->id}/points", [
            'points_per_unit' => 'abc',
        ])->assertStatus(422);
    }

    public function test_value_over_cap_is_rejected(): void
    {
        $this->actingAsAdmin(withPermission: true);
        $unit = $this->makeProductUnit();

        $this->patchJson("/api/admin/product-units/{$unit->id}/points", [
            'points_per_unit' => 1000001,
        ])->assertStatus(422);
    }
}
