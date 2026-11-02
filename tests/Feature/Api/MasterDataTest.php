<?php

namespace Tests\Feature\Api;

use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductUnitBlacklist;
use App\Models\Setting;
use App\Models\StockProductUnit;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    public function test_uom_crud_status_codes_and_shapes(): void
    {
        $this->actingAsAdmin();

        $created = $this->postJson('/api/uoms', ['name' => 'karung'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'karung');
        $id = $created->json('data.id');

        $this->getJson("/api/uoms/{$id}")->assertOk()->assertJsonPath('data.id', $id);

        $this->putJson("/api/uoms/{$id}", ['name' => 'sak'])
            ->assertStatus(202)
            ->assertJsonPath('data.name', 'sak');

        $this->deleteJson("/api/uoms/{$id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Data deleted successfully']);

        $this->assertDatabaseMissing('uoms', ['id' => $id]);
        $this->getJson("/api/uoms/{$id}")->assertNotFound()->assertExactJson(['message' => 'Data tidak ditemukan!']);
    }

    public function test_uom_index_pagination_keeps_links_without_query_string_and_no_per_page_cap(): void
    {
        $this->actingAsAdmin();
        Uom::create(['name' => 'a-uom']);
        Uom::create(['name' => 'b-uom']);

        $response = $this->getJson('/api/uoms?per_page=1&filter[name]=uom&sort=-name')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'b-uom')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
        $this->assertSame(url('/api/uoms').'?page=1', $response->json('links.first'));
    }

    public function test_index_per_page_is_not_capped(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/uoms?per_page=500')->assertOk()->assertJsonPath('meta.per_page', 500);
    }

    public function test_index_per_page_falls_back_to_15(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/uoms?per_page=0')->assertOk()->assertJsonPath('meta.per_page', 15);
    }

    public function test_known_quirk_controller_instance_keeps_the_first_per_page_within_one_app(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/uoms?per_page=1')->assertOk()->assertJsonPath('meta.per_page', 1);
        $this->getJson('/api/uoms?per_page=500')->assertOk()->assertJsonPath('meta.per_page', 1);
    }

    public function test_index_rejects_unknown_filters_and_sorts(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/uoms?filter[unknown]=x')->assertStatus(400);
        $this->getJson('/api/uoms?sort=unknown')->assertStatus(400);
    }

    public function test_write_endpoints_require_permissions(): void
    {
        $this->actingAsStaff();

        $this->postJson('/api/uoms', ['name' => 'x'])->assertForbidden();
        $this->postJson('/api/suppliers', [])->assertForbidden();
        $this->postJson('/api/product-categories', [])->assertForbidden();
        $this->postJson('/api/product-brands', [])->assertForbidden();
        $this->postJson('/api/products', [])->assertForbidden();
        $this->postJson('/api/warehouses', [])->assertForbidden();
        $this->getJson('/api/uoms')->assertOk();
        $this->getJson('/api/warehouses')->assertOk();
    }

    public function test_supplier_crud_and_search_filter(): void
    {
        $this->actingAsAdmin();
        $payload = ['code' => 'SUP-1', 'name' => 'Alpha Feed', 'email' => 'alpha@test.com', 'phone' => '0811', 'address' => 'Jl. A'];

        $id = $this->postJson('/api/suppliers', $payload)->assertStatus(201)->json('data.id');
        Supplier::create(['code' => 'SUP-2', 'name' => 'Beta', 'email' => 'beta@test.com', 'phone' => '0999', 'address' => 'Jl. B']);

        $this->getJson('/api/suppliers?filter[search]=0811')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'SUP-1');

        $this->putJson("/api/suppliers/{$id}", [...$payload, 'name' => 'Alpha 2'])
            ->assertStatus(202)
            ->assertJsonPath('data.name', 'Alpha 2');

        $this->deleteJson("/api/suppliers/{$id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
    }

    public function test_product_category_and_brand_crud(): void
    {
        $this->actingAsAdmin();

        foreach (['product-categories' => ProductCategory::class, 'product-brands' => ProductBrand::class] as $uri => $model) {
            $id = $this->postJson("/api/{$uri}", ['name' => "Name {$uri}"])
                ->assertStatus(201)
                ->assertJsonPath('data.name', "Name {$uri}")
                ->json('data.id');

            $this->getJson("/api/{$uri}?filter[name]=Name")->assertOk()->assertJsonPath('data.0.id', $id);
            $this->putJson("/api/{$uri}/{$id}", ['name' => 'Renamed'])->assertStatus(202)->assertJsonPath('data.name', 'Renamed');
            $this->deleteJson("/api/{$uri}/{$id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
        }
    }

    public function test_product_crud_returns_message_responses(): void
    {
        $this->actingAsAdmin();
        $category = ProductCategory::create(['name' => 'Cat']);
        $brand = ProductBrand::create(['name' => 'Brand']);
        $payload = ['company' => 'pas', 'name' => 'Pakan A', 'product_category_id' => $category->id, 'product_brand_id' => $brand->id];

        $this->postJson('/api/products', $payload)
            ->assertStatus(201)
            ->assertExactJson(['message' => 'Data created successfully']);

        $product = \App\Models\Product::where('name', 'Pakan A')->firstOrFail();

        $this->getJson('/api/products?filter[company]=pas')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.product_category.name', 'Cat')
            ->assertJsonPath('data.0.product_brand.name', 'Brand');

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.product_category.name', 'Cat');

        $this->putJson("/api/products/{$product->id}", [...$payload, 'name' => 'Pakan B'])
            ->assertOk()
            ->assertExactJson(['message' => 'Data updated successfully']);

        $this->deleteJson("/api/products/{$product->id}")->assertOk();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_product_sample_import_downloads_the_template(): void
    {
        $this->actingAsAdmin();

        $this->get('/api/products/sample-import')->assertOk()->assertDownload('products.xlsx');
    }

    public function test_warehouse_store_creates_stock_slots_and_crud_works(): void
    {
        $this->actingAsAdmin();
        $productUnit = $this->makeProductUnit();

        $response = $this->postJson('/api/warehouses', ['code' => 'WHX', 'name' => 'Gudang X', 'company_name' => 'PT X'])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'WHX');
        $id = $response->json('data.id');

        $this->assertTrue(StockProductUnit::where('warehouse_id', $id)->where('product_unit_id', $productUnit->id)->exists());

        $this->getJson('/api/warehouses?filter[is_for_marketplace]=0')->assertOk()->assertJsonFragment(['code' => 'WHX']);
        $this->getJson("/api/warehouses/{$id}")->assertOk()->assertJsonPath('data.name', 'Gudang X');
        $this->putJson("/api/warehouses/{$id}", ['name' => 'Gudang Y'])->assertStatus(202)->assertJsonPath('data.name', 'Gudang Y');
        $this->deleteJson("/api/warehouses/{$id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
        $this->assertSoftDeleted('warehouses', ['id' => $id]);
        $this->getJson('/api/warehouses/999999')->assertNotFound()->assertExactJson(['message' => 'Data tidak ditemukan!']);
    }

    public function test_product_unit_blacklist_store_index_and_destroy(): void
    {
        $user = $this->actingAsAdmin();
        $unitA = $this->makeProductUnit();
        $unitB = $this->makeProductUnit();

        $this->postJson('/api/product-unit-blacklists', ['product_unit_ids' => [$unitA->id, $unitB->id]])
            ->assertOk()
            ->assertExactJson(['message' => '2 inserted successfully']);

        $this->postJson('/api/product-unit-blacklists', ['product_unit_ids' => [$unitA->id]])
            ->assertOk()
            ->assertExactJson(['message' => '0 inserted successfully']);

        $this->postJson('/api/product-unit-blacklists', [])->assertStatus(422)->assertJsonValidationErrors(['product_unit_ids']);

        $this->getJson("/api/product-unit-blacklists?filter[product_unit_id]={$unitA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson("/api/product-unit-blacklists/{$unitA->id}")->assertForbidden();

        Sanctum::actingAs($user, ['*']);
        $this->deleteJson("/api/product-unit-blacklists/{$unitA->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Data deleted successfully']);

        $this->assertSame([$unitB->id], ProductUnitBlacklist::pluck('product_unit_id')->all());
    }

    public function test_settings_require_full_token_and_update_returns_202(): void
    {
        $user = $this->actingAsAdmin();
        $setting = Setting::create(['key' => 'tax_value', 'value' => '11']);

        $this->getJson('/api/settings')->assertForbidden();
        $this->putJson("/api/settings/{$setting->id}", ['value' => '12'])->assertForbidden();

        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/settings')->assertOk()->assertJsonPath('data.0.value', 11);
        $this->putJson("/api/settings/{$setting->id}", [])->assertStatus(422)->assertJsonValidationErrors(['value']);
        $this->putJson("/api/settings/{$setting->id}", ['value' => '12'])
            ->assertStatus(202)
            ->assertJsonPath('data.value', 12);
    }

    public function test_master_data_reads_do_not_depend_on_warehouse_assignment(): void
    {
        $this->makeWarehouse();
        $this->actingAsStaff();

        $this->assertGreaterThan(0, Warehouse::count());
        $this->getJson('/api/warehouses')->assertOk()->assertJsonCount(Warehouse::count(), 'data');
    }
}
