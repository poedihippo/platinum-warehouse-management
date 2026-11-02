<?php

namespace Tests\Feature\Api;

use App\Models\AdjustmentRequest;
use App\Models\DeliveryOrder;
use App\Models\ProductUnitRelation;
use App\Models\SalesOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class DeliveryOrderTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    private function context(int $qty = 3): array
    {
        $admin = $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $unit = $this->makeProductUnit(true);
        $spu = $this->spu($unit, $warehouse);
        $so = $this->makeSalesOrder($warehouse, [[$unit, $qty]]);
        $do = $this->makeDeliveryOrder($so);

        return [$admin, $warehouse, $unit, $spu, $so, $do, $do->details->first()];
    }

    public function test_store_update_index_show_and_attach(): void
    {
        [, $warehouse, $unit, , $so, $do] = $this->context();

        $created = $this->postJson('/api/delivery-orders', [
            'company' => 'pas', 'invoice_no' => 'DO-MANUAL-1', 'reseller_id' => $so->reseller_id, 'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-10-01 10:00:00', 'shipment_estimation_datetime' => '2026-10-02 10:00:00',
        ])->assertStatus(201)->assertJsonPath('data.invoice_no', 'DO-MANUAL-1');
        $newId = $created->json('data.id');

        $this->getJson('/api/delivery-orders?filter[invoice_no]=DO-MANUAL')->assertOk()->assertJsonPath('data.0.id', $newId);
        $this->getJson("/api/delivery-orders/{$do->id}")->assertOk()->assertJsonPath('data.id', $do->id);
        $this->getJson("/api/delivery-orders/get-by-so-detail/{$so->details->first()->id}")->assertOk()->assertJsonPath('data.delivery_order_id', $do->id);

        $so2 = $this->makeSalesOrder($warehouse, [[$unit, 2]]);
        $this->postJson("/api/delivery-orders/{$newId}/attach", ['details' => [['sales_order_detail_id' => $so2->details->first()->id, 'qty' => 2]]])
            ->assertOk()->assertExactJson(['message' => '1 Sales order berhasil ditambahkan ke delivery order']);
        $this->getJson("/api/delivery-orders/{$newId}/details")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_verification_single_group_and_guards(): void
    {
        [, , , $spu, , $do, $detail] = $this->context(3);
        $single = $this->makeStocks($spu, 1)->first();
        $group = $this->makeGroupedStock($spu, 2);
        $url = "/api/delivery-orders/{$do->id}/verification/{$detail->id}";

        $this->postJson($url, ['stock_id' => 'nope'])->assertStatus(400)->assertExactJson(['message' => 'Stok produk tidak sesuai']);
        $this->postJson($url, ['stock_id' => $single->id])->assertStatus(201)->assertJsonPath('data.stock_id', $single->id);
        $this->postJson($url, ['stock_id' => $single->id])->assertStatus(400);

        $this->postJson($url, ['stock_id' => $group->id])->assertStatus(201);
        $this->assertSame(3, (int) $detail->salesOrderDetail->fresh()->fulfilled_qty);
        $this->assertSame(4, SalesOrderItem::where('delivery_order_detail_id', $detail->id)->count());

        $extra = $this->makeStocks($spu, 1)->first();
        $this->postJson($url, ['stock_id' => $extra->id])->assertStatus(400)->assertExactJson(['message' => 'Qty sudah terpenuhi']);

        $this->getJson("/api/delivery-orders/{$do->id}/details/{$detail->id}/items")->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_done_undone_with_bundle_relation_and_return(): void
    {
        $admin = $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $base = $this->makeProductUnit(true);
        $packaging = $this->makeProductUnit(false);
        $bundle = $this->makeProductUnit(true, ['refer_id' => $base->id, 'refer_qty' => 1]);
        ProductUnitRelation::create(['product_unit_id' => $bundle->id, 'related_product_unit_id' => $packaging->id, 'qty' => 2]);
        $packagingSpu = $this->spu($packaging, $warehouse);
        $packagingSpu->update(['qty' => 10]);
        $baseSpu = $this->spu($base, $warehouse);
        $stocks = $this->makeStocks($baseSpu, 2);
        $so = $this->makeSalesOrder($warehouse, [[$bundle, 2]]);
        $do = $this->makeDeliveryOrder($so);
        $detail = $do->details->first();

        foreach ($stocks as $stock) {
            $this->postJson("/api/delivery-orders/{$do->id}/verification/{$detail->id}", ['stock_id' => $stock->id])->assertStatus(201);
        }

        $this->putJson("/api/delivery-orders/{$do->id}/done", ['is_done' => true])
            ->assertStatus(202)->assertExactJson(['message' => 'Data set as Done']);
        $this->assertSame(6, (int) $packagingSpu->fresh()->qty);
        $soDetail = $so->details->first();
        $this->assertHistory($soDetail, ['value' => 2, 'is_increment' => 0, 'description' => $do->invoice_no.' - Verified DO', 'user_id' => $admin->id, 'stock_product_unit_id' => $baseSpu->id]);
        $this->assertHistory($packagingSpu, ['value' => 4, 'is_increment' => 0]);

        $this->putJson("/api/delivery-orders/{$do->id}/done", ['is_done' => true])
            ->assertStatus(400)->assertExactJson(['message' => 'Tidak dapat update status yang sama.']);
        $this->postJson("/api/delivery-orders/{$do->id}/verification/{$detail->id}", ['stock_id' => $stocks[0]->id])
            ->assertStatus(404)->assertExactJson(['message' => 'Delivery Order sudah diselesaikan. Batalkan untuk dapat scan lagi']);

        $this->putJson("/api/delivery-orders/{$do->id}/done", ['is_done' => false])->assertStatus(202)->assertExactJson(['message' => 'Data set as Pending']);
        $this->assertSame(10, (int) $packagingSpu->fresh()->qty);
        $this->assertHistory($soDetail, ['is_increment' => 1, 'description' => $do->invoice_no.' - Unverified DO']);

        $this->postJson("/api/delivery-orders/{$do->id}/return", ['ids' => [$stocks[0]->id, $stocks[1]->id]])
            ->assertOk()->assertExactJson(['message' => '2 stocks returned  successfully']);
        $this->assertSame(0, (int) $soDetail->fresh()->fulfilled_qty);
        $adjustment = AdjustmentRequest::firstOrFail();
        $this->assertSame(1, (int) $adjustment->value);
        $this->assertHistory($adjustment, ['value' => 1, 'is_increment' => 1]);
    }

    public function test_known_quirk_return_answers_200_with_the_exception_text(): void
    {
        [, , , $spu, , $do] = $this->context();
        $stock = $this->makeStocks($spu, 1)->first();

        $response = $this->postJson("/api/delivery-orders/{$do->id}/return", ['ids' => [$stock->id]])->assertOk();
        $this->assertStringContainsString('Stock not found', $response->getContent());
    }

    public function test_detail_update_reset_and_destroy(): void
    {
        [, , , $spu, , $do, $detail] = $this->context(3);
        $stock = $this->makeStocks($spu, 1)->first();
        $this->postJson("/api/delivery-orders/{$do->id}/verification/{$detail->id}", ['stock_id' => $stock->id])->assertStatus(201);

        $this->putJson("/api/delivery-orders/{$do->id}/details/{$detail->id}", ['qty' => 2])->assertOk()->assertJsonPath('data.qty', 2);
        $this->putJson("/api/delivery-orders/{$do->id}/details/{$detail->id}/reset-verified-stock")->assertOk();
        $this->assertSame(0, SalesOrderItem::count());
        $this->assertSame(0, (int) $detail->salesOrderDetail->fresh()->fulfilled_qty);

        $this->deleteJson("/api/delivery-orders/{$do->id}/details/{$detail->id}")->assertForbidden();
        $this->deleteJson("/api/delivery-orders/{$do->id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
        $this->assertNull(DeliveryOrder::find($do->id));
    }
}
