<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\Uom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class ProductUnitTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'PU-'.uniqid(),
            'uom_id' => Uom::create(['name' => 'uom-'.uniqid()])->id,
            'name' => 'Pakan 1kg',
            'product_id' => $this->makeProduct()->id,
            'price' => 15000,
            'is_generate_qr' => true,
            'is_ppn' => false,
            'is_auto_stock' => true,
        ], $overrides);
    }

    public function test_crud_status_codes_and_stock_slots(): void
    {
        $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $payload = $this->payload();

        $this->postJson('/api/product-units', $payload)
            ->assertStatus(201)
            ->assertExactJson(['message' => 'Data created successfully']);

        $unit = ProductUnit::where('code', $payload['code'])->firstOrFail();
        $this->assertNotNull($this->spu($unit, $warehouse));

        $this->getJson("/api/product-units/{$unit->id}")
            ->assertOk()
            ->assertJsonPath('data.code', $payload['code'])
            ->assertJsonPath('data.uom.id', $payload['uom_id']);

        $this->getJson('/api/product-units?filter[name]=Pakan&include=product,uom&sort=-id')
            ->assertOk()
            ->assertJsonPath('data.0.id', $unit->id)
            ->assertJsonPath('data.0.product.id', $payload['product_id']);

        $this->putJson("/api/product-units/{$unit->id}", [...$payload, 'name' => 'Pakan 2kg'])
            ->assertOk()
            ->assertExactJson(['message' => 'Data updated successfully']);
        $this->assertSame('Pakan 2kg', $unit->fresh()->name);

        $this->deleteJson("/api/product-units/{$unit->id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
        $this->assertSoftDeleted('product_units', ['id' => $unit->id]);
        $this->getJson("/api/product-units/{$unit->id}")->assertNotFound();
    }

    public function test_index_rejects_invalid_include_and_sort(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/product-units?include=unknown')->assertStatus(400);
        $this->getJson('/api/product-units?sort=unknown')->assertStatus(400);
    }

    public function test_relations_create_update_and_guards(): void
    {
        $this->actingAsAdmin();
        $base = $this->makeProductUnit(true);
        $packaging = $this->makeProductUnit(false);
        $qrUnit = $this->makeProductUnit(true);

        $this->postJson("/api/product-units/{$base->id}/create-relations", [
            'code' => 'BUNDLE-QR',
            'name' => 'Bundle',
            'price' => 1,
            'refer_qty' => 2,
            'related_product_units' => [['id' => $qrUnit->id, 'qty' => 1]],
        ])->assertStatus(422)->assertJsonPath('errors.related_product_units.0', 'Can not related to product unit that have is_generate_qr = true.');

        $this->postJson("/api/product-units/{$base->id}/create-relations", [
            'code' => 'BUNDLE-1',
            'name' => 'Bundle',
            'price' => 30000,
            'refer_qty' => 2,
            'related_product_units' => [['id' => $packaging->id, 'qty' => 3]],
        ])->assertStatus(201)->assertExactJson(['message' => 'Data created successfully']);

        $bundle = ProductUnit::where('code', 'BUNDLE-1')->firstOrFail();
        $this->assertSame($base->id, $bundle->refer_id);
        $this->assertSame($base->product_id, $bundle->product_id);
        $this->assertSame([[$packaging->id, 3]], $bundle->relations->map(fn ($r) => [$r->related_product_unit_id, $r->qty])->all());

        $this->putJson("/api/product-units/{$bundle->id}", $this->payload(['code' => 'BUNDLE-1']))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Product Grouping can not update here');

        $this->putJson("/api/product-units/{$base->id}/update-relations/{$bundle->id}", [
            'name' => 'Bundle 2',
            'price' => 31000,
            'refer_qty' => 4,
            'related_product_units' => [['id' => $packaging->id, 'qty' => 5]],
        ])->assertOk()->assertExactJson(['message' => 'Data updated successfully']);

        $bundle->refresh();
        $this->assertSame('Bundle 2', $bundle->name);
        $this->assertSame($base->id, $bundle->refer_id);
        $this->assertSame([[$packaging->id, 5]], $bundle->relations->map(fn ($r) => [$r->related_product_unit_id, $r->qty])->all());

        $this->putJson("/api/product-units/{$packaging->id}/update-relations/{$bundle->id}", [
            'name' => 'x', 'price' => 1, 'refer_qty' => 1, 'related_product_units' => [['id' => $packaging->id, 'qty' => 1]],
        ])->assertNotFound();
    }

    public function test_change_product_and_user_price(): void
    {
        $this->actingAsAdmin();
        $unit = $this->makeProductUnit();
        $otherProduct = $this->makeProduct(['company' => 'pa']);

        $this->putJson("/api/product-units/{$unit->id}/change-product", ['company' => 'pa', 'product_id' => $otherProduct->id])
            ->assertStatus(202)
            ->assertJsonPath('data.id', $unit->id);
        $this->assertSame($otherProduct->id, $unit->fresh()->product_id);

        $warehouse = $this->makeWarehouse();
        $reseller = $this->makeUser(['type' => 'reseller']);
        $order = SalesOrder::create([
            'is_invoice' => true, 'invoice_no' => 'X/'.uniqid(), 'type' => 'default', 'user_id' => $reseller->id,
            'reseller_id' => $reseller->id, 'warehouse_id' => $warehouse->id, 'transaction_date' => now(),
            'shipment_estimation_datetime' => now(), 'price' => 0, 'additional_discount' => 0, 'auto_discount' => 0, 'shipment_fee' => 0,
        ]);
        SalesOrderDetail::create(['sales_order_id' => $order->id, 'product_unit_id' => $unit->id, 'warehouse_id' => $warehouse->id, 'qty' => 1, 'unit_price' => 12345, 'total_price' => 12345]);

        $this->getJson("/api/product-units/{$unit->id}/user-price/{$reseller->id}?sort=-created_at")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.unit_price', 12345)
            ->assertJsonPath('data.0.warehouse.name', $warehouse->name);

        $this->getJson("/api/product-units/{$unit->id}/user-price/999999")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_sample_import_download(): void
    {
        $this->actingAsAdmin();

        $this->get('/api/product-units/sample-import')->assertOk()->assertDownload('product_units.xlsx');
    }

    public function test_public_product_lookup_by_stock(): void
    {
        $warehouse = $this->makeWarehouse();
        $unit = $this->makeProductUnit();
        $stock = $this->makeStocks($this->spu($unit, $warehouse), 1, ['expired_date' => '2027-01-31'])->first();

        $this->getJson("/product-units/{$stock->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $unit->id)
            ->assertJsonPath('data.code', $unit->code)
            ->assertJsonPath('data.product.product_category.id', $unit->product->product_category_id);
        $this->assertStringStartsWith('2027-01-31', $this->getJson("/product-units/{$stock->id}")->json('data.expired_date'));

        $this->getJson('/product-units/unknown-stock')
            ->assertStatus(400)
            ->assertExactJson(['message' => 'Product tidak ditemukan.']);
    }

    public function test_points_update_through_warehouse_and_loyalty_routes(): void
    {
        Permission::firstOrCreate(['name' => 'manage loyalty points', 'guard_name' => 'web']);
        $admin = $this->makeUser();
        $admin->givePermissionTo('manage loyalty points');
        $eligible = $this->makeProductUnit(true);
        $eligible->forceFill(['loyalty_eligible' => true])->save();
        $ineligible = $this->makeProductUnit(true);
        $ineligible->forceFill(['loyalty_eligible' => false, 'points_per_unit' => 3])->save();

        Sanctum::actingAs($admin, ['warehouse']);
        $this->patchJson("/api/admin/product-units/{$eligible->id}/points", ['points_per_unit' => 15])
            ->assertOk()
            ->assertJsonPath('data.id', $eligible->id);
        $this->assertSame(15, (int) $eligible->fresh()->points_per_unit);

        $this->patchJson("/api/admin/product-units/{$ineligible->id}/points", ['points_per_unit' => 15])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Product unit ini tidak memenuhi syarat untuk program loyalty.']);
        $this->patchJson("/api/admin/product-units/{$eligible->id}/points", ['points_per_unit' => -1])->assertStatus(422);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/loyalty/points/{$eligible->id}", ['points_per_unit' => 20])
            ->assertOk()
            ->assertJsonPath('data.points_per_unit', 20);

        $index = $this->getJson('/api/admin/loyalty/points?per_page=999')->assertOk();
        $index->assertJsonPath('meta.per_page', 200)->assertJsonPath('data.0.id', $eligible->id);
        $this->getJson('/api/admin/loyalty/points?q='.urlencode($ineligible->code))->assertOk()->assertJsonCount(1, 'data');

        $other = $this->makeUser();
        Sanctum::actingAs($other);
        $this->getJson('/api/admin/loyalty/points')
            ->assertForbidden()
            ->assertExactJson(['message' => 'You are not authorized to manage loyalty points.']);
        $this->patchJson("/api/admin/loyalty/points/{$eligible->id}", [])
            ->assertForbidden();
    }

    public function test_loyalty_product_unit_search_permission(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/loyalty/product-units?q=x')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Anda tidak memiliki izin untuk meninjau klaim.']);
    }
}
