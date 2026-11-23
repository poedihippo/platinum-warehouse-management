<?php

namespace Tests\Feature\Api;

use App\Exports\StockExport;
use App\Models\ProductUnit;
use App\Models\ProductUnitRelation;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\SalesOrderItem;
use App\Models\Uom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class MultiplyQtyTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    private function productUnitPayload(array $overrides = []): array
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

    private function salesOrderPayload($warehouse, $unit, $reseller, int $qty = 2): array
    {
        return [
            'company' => 'pas',
            'type' => 'default',
            'reseller_id' => $reseller->id,
            'invoice_no' => 'SO-MULTIPLY-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-11-01 10:00:00',
            'shipment_estimation_datetime' => '2026-11-02 10:00:00',
            'shipment_fee' => 0,
            'additional_discount' => 0,
            'items' => [[
                'product_unit_id' => $unit->id, 'qty' => $qty, 'unit_price' => 10000, 'discount' => 0, 'tax' => 0,
                'total_price' => 10000 * $qty, 'warehouse_id' => $warehouse->id, 'multiply_qty' => 999,
            ]],
        ];
    }

    private function salesOrderWithMultiply(int $qty, int $multiplyQty, array $attributes = []): array
    {
        $warehouse = $this->makeWarehouse();
        $unit = $this->makeProductUnit(true, ['multiply_qty' => $multiplyQty]);
        $so = $this->makeSalesOrder($warehouse, [[$unit, $qty]], $attributes);
        $so->details->each->update(['multiply_qty' => $multiplyQty]);

        return [$warehouse, $unit, $this->spu($unit, $warehouse), $so->load('details')];
    }

    public function test_product_unit_multiply_qty_defaults_to_one_and_can_be_updated(): void
    {
        $this->actingAsAdmin();
        $payload = $this->productUnitPayload();

        $this->postJson('/api/product-units', $payload)->assertStatus(201);
        $unit = ProductUnit::where('code', $payload['code'])->firstOrFail();
        $this->assertSame(1, $unit->multiply_qty);
        $this->getJson("/api/product-units/{$unit->id}")->assertOk()->assertJsonPath('data.multiply_qty', 1);

        $this->putJson("/api/product-units/{$unit->id}", [...$payload, 'multiply_qty' => 100])->assertOk();
        $this->assertSame(100, $unit->fresh()->multiply_qty);

        $this->putJson("/api/product-units/{$unit->id}", $payload)->assertOk();
        $this->assertSame(100, $unit->fresh()->multiply_qty);

        $this->putJson("/api/product-units/{$unit->id}", [...$payload, 'multiply_qty' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('multiply_qty');

        $created = $this->productUnitPayload(['multiply_qty' => 50]);
        $this->postJson('/api/product-units', $created)->assertStatus(201);
        $this->assertSame(50, ProductUnit::where('code', $created['code'])->value('multiply_qty'));
    }

    public function test_sales_order_detail_snapshots_product_unit_multiply_qty(): void
    {
        $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $unit = $this->makeProductUnit(true, ['multiply_qty' => 100]);
        $reseller = $this->makeUser(['type' => 'reseller', 'company' => 'pas']);

        $created = $this->postJson('/api/sales-orders', $this->salesOrderPayload($warehouse, $unit, $reseller))->assertStatus(201);
        $salesOrder = SalesOrder::findOrFail($created->json('data.id'));
        $detail = $salesOrder->details()->firstOrFail();
        $this->assertSame(2, $detail->qty);
        $this->assertSame(100, $detail->multiply_qty);
        $this->assertSame(200, $detail->stockQty());

        $unit->update(['multiply_qty' => 10]);
        $this->assertSame(100, $detail->fresh()->multiply_qty);

        $this->getJson("/api/sales-orders/{$salesOrder->id}")
            ->assertOk()
            ->assertJsonPath('data.details.0.multiply_qty', 100)
            ->assertJsonPath('data.unverified_details_count', 1);
    }

    public function test_delivery_order_verification_requires_qty_times_multiply_qty_stocks(): void
    {
        $this->actingAsAdmin();
        [, , $spu, $so] = $this->salesOrderWithMultiply(2, 3);
        $do = $this->makeDeliveryOrder($so);
        $detail = $do->details->first();
        $url = "/api/delivery-orders/{$do->id}/verification/{$detail->id}";

        foreach ($this->makeStocks($spu, 6) as $stock) {
            $this->postJson($url, ['stock_id' => $stock->id])->assertStatus(201);
        }

        $this->assertSame(6, $so->details->first()->fresh()->fulfilled_qty);

        $extra = $this->makeStocks($spu, 1)->first();
        $this->postJson($url, ['stock_id' => $extra->id])->assertStatus(400)->assertExactJson(['message' => 'Qty sudah terpenuhi']);

        $this->getJson("/api/delivery-orders/{$do->id}/details")
            ->assertOk()
            ->assertJsonPath('data.0.qty', 2)
            ->assertJsonPath('data.0.total_verified_stock', 6)
            ->assertJsonPath('data.0.sales_order_detail.multiply_qty', 3);

        $this->getJson("/api/sales-orders/{$so->id}")->assertOk()->assertJsonPath('data.unverified_details_count', 0);
    }

    public function test_delivery_order_group_scan_cannot_exceed_qty_times_multiply_qty(): void
    {
        $this->actingAsAdmin();
        [, , $spu, $so] = $this->salesOrderWithMultiply(1, 3);
        $do = $this->makeDeliveryOrder($so);
        $detail = $do->details->first();
        $url = "/api/delivery-orders/{$do->id}/verification/{$detail->id}";

        $this->postJson($url, ['stock_id' => $this->makeGroupedStock($spu, 4)->id])
            ->assertStatus(400)
            ->assertExactJson(['message' => 'Jumlah barang yang di scan (4) melebihi qty. Tersisa 3 lagi']);

        $this->postJson($url, ['stock_id' => $this->makeGroupedStock($spu, 3)->id])->assertStatus(201);
        $this->assertSame(3, $so->details->first()->fresh()->fulfilled_qty);
    }

    public function test_delivery_order_done_moves_scanned_stocks_and_multiplied_bundle_components(): void
    {
        $admin = $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $base = $this->makeProductUnit(true);
        $packaging = $this->makeProductUnit(false);
        $bundle = $this->makeProductUnit(true, ['refer_id' => $base->id, 'refer_qty' => 2, 'multiply_qty' => 3]);
        ProductUnitRelation::create(['product_unit_id' => $bundle->id, 'related_product_unit_id' => $packaging->id, 'qty' => 1]);
        $packagingSpu = $this->spu($packaging, $warehouse);
        $packagingSpu->update(['qty' => 10]);
        $baseSpu = $this->spu($base, $warehouse);
        $so = $this->makeSalesOrder($warehouse, [[$bundle, 2]]);
        $so->details->each->update(['multiply_qty' => 3]);
        $do = $this->makeDeliveryOrder($so->load('details'));
        $detail = $do->details->first();

        foreach ($this->makeStocks($baseSpu, 6) as $stock) {
            $this->postJson("/api/delivery-orders/{$do->id}/verification/{$detail->id}", ['stock_id' => $stock->id])->assertStatus(201);
        }

        $this->putJson("/api/delivery-orders/{$do->id}/done", ['is_done' => true])->assertStatus(202);

        $this->assertHistory($so->details->first(), [
            'value' => 6, 'is_increment' => 0, 'user_id' => $admin->id, 'stock_product_unit_id' => $baseSpu->id,
        ]);
        $this->assertSame(7, (int) $packagingSpu->fresh()->qty);
        $this->assertHistory($packagingSpu, ['value' => 3, 'is_increment' => 0]);
    }

    public function test_marketplace_sales_order_verification_requires_qty_times_multiply_qty_stocks(): void
    {
        $this->actingAsAdmin();
        [, , $spu, $so] = $this->salesOrderWithMultiply(1, 2, ['marketplace' => 'shopee']);
        $detail = $so->details->first();
        $url = "/api/sales-orders/{$so->id}/verification/{$detail->id}";
        [$first, $second, $third] = $this->makeStocks($spu, 3)->all();

        $this->postJson($url, ['stock_id' => $first->id])->assertStatus(201);
        $this->getJson('/api/sales-orders?filter[is_mergeable]=1')->assertOk()->assertJsonCount(0, 'data');

        $this->postJson($url, ['stock_id' => $second->id])->assertStatus(201);
        $this->postJson($url, ['stock_id' => $third->id])->assertStatus(400)->assertJsonPath('message', 'Qty sudah terpenuhi');
        $this->getJson('/api/sales-orders?filter[is_mergeable]=1')->assertOk()->assertJsonPath('data.0.id', $so->id);
    }

    public function test_merge_keeps_the_multiply_qty_snapshot_of_the_sources(): void
    {
        $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $unit = $this->makeProductUnit(true, ['multiply_qty' => 2]);
        $spu = $this->spu($unit, $warehouse);
        $sources = collect([1, 2])->map(function () use ($warehouse, $unit, $spu) {
            $so = $this->makeSalesOrder($warehouse, [[$unit, 1]], ['marketplace' => 'shopee']);
            $detail = $so->details->first();
            $detail->update(['multiply_qty' => 2]);

            foreach ($this->makeStocks($spu, 2) as $stock) {
                $this->postJson("/api/sales-orders/{$so->id}/verification/{$detail->id}", ['stock_id' => $stock->id])->assertStatus(201);
            }

            return $so;
        });

        $unit->update(['multiply_qty' => 5]);

        $merged = $this->postJson('/api/sales-orders/merge', [
            'sales_order_ids' => $sources->pluck('id')->all(),
            'marketplace' => 'shopee',
            'invoice_no' => 'SO-MERGE-'.uniqid(),
            'delivery_order_invoice_no' => 'DO-MERGE-'.uniqid(),
            'transaction_date' => '2026-11-03 10:00:00',
        ])->assertSuccessful();

        $detail = SalesOrderDetail::where('sales_order_id', $merged->json('data.id'))->sole();
        $this->assertSame(2, $detail->qty);
        $this->assertSame(2, $detail->multiply_qty);
        $this->assertSame(4, $detail->fulfilled_qty);
        $this->assertSame(4, SalesOrderItem::where('sales_order_detail_id', $detail->id)->count());
    }

    public function test_stock_index_returns_the_qr_count_with_the_product_unit_multiply_qty(): void
    {
        $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $unit = $this->makeProductUnit(true, ['multiply_qty' => 10]);
        $spu = $this->spu($unit, $warehouse);
        $this->makeStocks($spu, 15, ['is_stock' => true]);

        $this->getJson("/api/stocks?filter[id]={$spu->id}")
            ->assertOk()
            ->assertJsonPath('data.0.qty', 15)
            ->assertJsonPath('data.0.product_unit.multiply_qty', 10);
    }

    public function test_stock_export_adds_multiply_and_accurate_qty_columns(): void
    {
        $this->actingAsAdmin();
        Excel::fake();
        $warehouse = $this->makeWarehouse(['name' => 'Gudang Multiply']);
        $unit = $this->makeProductUnit(true, ['code' => 'PU-MULTIPLY-10', 'multiply_qty' => 10]);
        $this->makeStocks($this->spu($unit, $warehouse), 105);

        $this->get('/api/stocks/export')->assertOk();

        Excel::matchByRegex();
        Excel::assertDownloaded('/^stock-.*\.xlsx$/', function (StockExport $export) {
            $html = preg_replace('/\s+/', '', $export->view()->render());

            $this->assertStringContainsString('<th>Multiply</th>', $html);
            $this->assertStringContainsString('<th>GudangMultiply(Accurate)</th>', $html);
            $this->assertStringContainsString('<td>PU-MULTIPLY-10</td>', $html);
            $this->assertMatchesRegularExpression('#<td>PU-MULTIPLY-10</td>.*?<td>105</td>.*?<td>10</td>.*?<td>10\.5</td>#', $html);

            return true;
        });
    }

    public function test_sales_order_and_delivery_order_xml_keep_the_accurate_qty(): void
    {
        $this->actingAsAdmin();
        [, , , $so] = $this->salesOrderWithMultiply(2, 100);
        $do = $this->makeDeliveryOrder($so);

        foreach (["/api/sales-orders/{$so->id}/export-xml", "/api/delivery-orders/{$do->id}/export-xml"] as $url) {
            $xml = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('<QUANTITY>2</QUANTITY>', $xml, $url);
            $this->assertStringNotContainsString('<QUANTITY>200</QUANTITY>', $xml, $url);
        }
    }
}
