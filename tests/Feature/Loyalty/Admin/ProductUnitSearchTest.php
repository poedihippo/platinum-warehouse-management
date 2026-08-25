<?php

namespace Tests\Feature\Loyalty\Admin;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Database\Factories\ProductFactory;
use Database\Factories\ProductUnitFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductUnitSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate([
            'name' => 'review claims',
            'guard_name' => 'web',
        ]);
    }

    /**
     * ProductUnit create events cascade into stock_product_units; this
     * test owns only the rows it needs, so bypass them.
     */
    private function makeUnit(string $productName, string $unitName, string $code, int $points): ProductUnit
    {
        return Model::withoutEvents(function () use ($productName, $unitName, $code, $points) {
            $product = ProductFactory::new()->create(['name' => $productName]);
            $unit = ProductUnitFactory::new()->create([
                'product_id' => $product->id,
                'name' => $unitName,
                'code' => $code,
            ]);
            $unit->points_per_unit = $points; // not fillable; set directly
            $unit->save();

            return $unit;
        });
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->givePermissionTo('review claims');
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function search(string $q): array
    {
        return $this->getJson('/api/admin/loyalty/product-units?q='.urlencode($q))
            ->assertOk()
            ->json('data');
    }

    /**
     * The admin UI renders "{product.name} - {unit.name}", a string that
     * lives in no single column. All three ways a reviewer might type it
     * must reach the same row.
     */
    public function test_search_matches_product_name_unit_name_and_code(): void
    {
        $target = $this->makeUnit('CZ Aqua', 'CZ Bacta Extrem', 'CZM014', 100);
        $this->makeUnit('Other Product', 'Other Unit', 'OTH001', 100);

        $this->actingAsAdmin();

        foreach (['CZ Aqua - CZ Bacta Extrem', 'Bacta Extrem', 'CZM014'] as $q) {
            $results = $this->search($q);

            $this->assertCount(1, $results, "query [$q] should match exactly one unit");
            $this->assertSame($target->id, $results[0]['id'], "query [$q] should return CZM014");
            $this->assertSame('CZ Aqua - CZ Bacta Extrem', $results[0]['name']);
            $this->assertSame('CZM014', $results[0]['code']);
        }
    }

    public function test_tokens_must_all_match_and_zero_point_units_stay_hidden(): void
    {
        $this->makeUnit('CZ Aqua', 'CZ Bacta Extrem', 'CZM014', 100);
        $this->makeUnit('CZ Aqua', 'CZ Bacta Mild', 'CZM015', 0);

        $this->actingAsAdmin();

        // 'Mild' only exists on the zero-point unit, which is never listed.
        $this->assertCount(0, $this->search('CZ Aqua Mild'));

        // A token matching nothing rules the row out, even though the rest hit.
        $this->assertCount(0, $this->search('CZ Bacta Nonexistent'));

        // Tokens may match across different columns of the same row.
        $this->assertCount(1, $this->search('Aqua CZM014'));
    }
}
