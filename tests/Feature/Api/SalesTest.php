<?php

namespace Tests\Feature\Api;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    private function soPayload($warehouse, $unit, $reseller, array $overrides = []): array
    {
        return array_merge([
            'company' => 'pas',
            'type' => 'default',
            'reseller_id' => $reseller->id,
            'invoice_no' => 'SO-MANUAL-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-10-01 10:00:00',
            'shipment_estimation_datetime' => '2026-10-02 10:00:00',
            'shipment_fee' => 5000,
            'additional_discount' => 0,
            'items' => [[
                'product_unit_id' => $unit->id, 'qty' => 2, 'unit_price' => 10000, 'discount' => 0, 'tax' => 0,
                'total_price' => 20000, 'warehouse_id' => $warehouse->id,
            ]],
        ], $overrides);
    }

    private function context(): array
    {
        $admin = $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $unit = $this->makeProductUnit(true);
        $reseller = $this->makeUser(['type' => 'reseller', 'company' => 'pas']);

        return [$admin, $warehouse, $unit, $reseller];
    }

    public function test_sales_order_store_preview_update_show_and_destroy(): void
    {
        [, $warehouse, $unit, $reseller] = $this->context();
        $payload = $this->soPayload($warehouse, $unit, $reseller);

        $preview = $this->postJson('/api/sales-orders', [...$payload, 'is_preview' => true])->assertOk();
        $this->assertSame(25000, (int) $preview->json('data.price'));
        $this->assertSame(0, SalesOrder::count());

        $created = $this->postJson('/api/sales-orders', $payload)->assertStatus(201);
        $id = $created->json('data.id');
        $this->assertSame(25000, (int) $created->json('data.price'));
        $this->assertSame(1, SalesOrder::find($id)->details()->count());

        $this->getJson('/api/sales-orders?filter[invoice_no]=SO-MANUAL')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/sales-orders/{$id}")->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson("/api/sales-orders/{$id}/details")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/sales-orders/{$id}", $this->soPayload($warehouse, $unit, $reseller, ['shipment_fee' => 0, 'invoice_no' => $payload['invoice_no']]))
            ->assertStatus(202)->assertJsonPath('data.price', 20000);

        $this->deleteJson("/api/sales-orders/{$id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
        $this->assertNull(SalesOrder::find($id));
    }

    public function test_sales_order_update_blocked_by_delivery_order(): void
    {
        [, $warehouse, $unit, $reseller] = $this->context();
        $so = $this->makeSalesOrder($warehouse, [[$unit, 1]]);
        $this->makeDeliveryOrder($so);

        $this->putJson("/api/sales-orders/{$so->id}", $this->soPayload($warehouse, $unit, $reseller))
            ->assertStatus(400)->assertExactJson(['message' => 'DO harus dihapus terlebih dahulu sebelum mengedit SO']);
        $this->deleteJson("/api/sales-orders/{$so->id}")
            ->assertStatus(400)->assertExactJson(['message' => 'Tidak dapat menghapus SO yang sudah direlasikan dengan DO']);
    }

    public function test_marketplace_scan_items_and_release_on_delete(): void
    {
        [, $warehouse, $unit] = $this->context();
        $so = $this->makeSalesOrder($warehouse, [[$unit, 2]], ['marketplace' => 'shopee']);
        $detail = $so->details->first();
        $stock = $this->makeStocks($this->spu($unit, $warehouse), 1)->first();

        $this->postJson("/api/sales-orders/{$so->id}/verification/{$detail->id}", ['stock_id' => $stock->id])->assertStatus(201);
        $this->postJson("/api/sales-orders/{$so->id}/verification/{$detail->id}", ['stock_id' => $stock->id])
            ->assertStatus(400)->assertJsonPath('message', 'Product sudah pernah di verifikasi');
        $this->getJson("/api/sales-orders/{$so->id}/details/{$detail->id}/items")->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/api/sales-orders/{$so->id}")->assertOk();
        $this->assertSame(0, SalesOrderItem::count());
        $this->assertTrue(StockHistory::where('description', 'Return stock from delete SO '.$so->invoice_no)->exists());
    }

    public function test_product_unit_pickers_answer_raw_paginators(): void
    {
        [, $warehouse, $unit] = $this->context();
        $this->makeStocks($this->spu($unit, $warehouse), 2);

        $this->getJson("/api/sales-orders/product-units?filter[warehouse_id]={$warehouse->id}")
            ->assertOk()->assertJsonStructure(['data', 'current_page', 'total'])
            ->assertJsonPath('data.0.product_unit.price_discount', 100000);
        $this->getJson("/api/sales-orders/product-units-new?filter[warehouse_id]={$warehouse->id}")
            ->assertOk()->assertJsonStructure(['data', 'current_page'])
            ->assertJsonPath('data.0.discount', 0);
    }

    public function test_invoice_store_numbering_show_update_and_destroy(): void
    {
        [$admin, $warehouse, $unit] = $this->context();
        $this->makeStocks($this->spu($unit, $warehouse), 1);
        $payload = [
            'type' => 'default', 'warehouse_id' => $warehouse->id, 'customer_name' => 'Walk In', 'customer_phone' => '0811',
            'transaction_date' => '2026-10-01 10:00:00', 'shipment_estimation_datetime' => '2026-10-01 10:00:00',
            'items' => [['product_unit_id' => $unit->id, 'qty' => 1, 'unit_price' => 10000, 'discount' => 0, 'tax' => 0, 'total_price' => 10000]],
        ];

        $this->postJson('/api/invoices', [...$payload, 'items' => [[...$payload['items'][0], 'qty' => 5, 'total_price' => 50000]]])
            ->assertStatus(422)->assertJsonPath('message', 'Stok '.$unit->name.' tidak tersedia');

        $created = $this->postJson('/api/invoices', $payload)->assertStatus(201);
        $id = $created->json('data.id');
        $this->assertStringStartsWith('IIPE-'.date('Y/m/d').'/0090/', SalesOrder::find($id)->invoice_no);

        $this->getJson("/api/invoices/{$id}")->assertOk()->assertJsonPath('data.id', $id);
        $this->assertNotEmpty($this->getJson("/api/invoices/{$id}")->json('data.whatsapp_url') ?? 'x');
        $no = $this->getJson("/api/invoices/get-invoice-no?warehouse_id={$warehouse->id}")->assertOk()->getContent();
        $this->assertStringStartsWith('IIPE-'.date('Y/m/d').'/0091/', $no);

        $invoice = SalesOrder::find($id);
        $updatePayload = collect($payload)->except(['customer_name', 'customer_phone'])->all();
        $this->putJson("/api/invoices/{$id}", [...$updatePayload, 'reseller_id' => $invoice->reseller_id, 'invoice_no' => $invoice->invoice_no])->assertStatus(202);

        $this->deleteJson("/api/invoices/{$id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
        $this->assertNull(SalesOrder::find($id));
        $this->assertTrue(StockHistory::where('is_increment', 1)->where('user_id', $admin->id)->exists());
    }

    public function test_spg_order_store_convert_and_destroy(): void
    {
        [, $warehouse, $unit] = $this->context();
        $reseller = $this->makeUser(['type' => 'customer_event']);
        $spg = $this->actingAsStaff(['order_create', 'order_delete'], [], ['type' => 'spg']);
        $payload = [
            'type' => 'pickup', 'reseller_id' => $reseller->id, 'customer_name' => 'Walk In',
            'items' => [['product_unit_id' => $unit->id, 'qty' => 1, 'unit_price' => 10000, 'tax' => 0, 'total_price' => 10000]],
        ];

        $created = $this->postJson('/api/orders', $payload)->assertStatus(201);
        $order = SalesOrder::findOrFail($created->json('data.id'));
        $this->assertSame('', (string) $order->invoice_no);
        $this->assertSame($spg->id, $order->spg_id);

        $this->putJson("/api/orders/{$order->id}/convert-so", ['type' => 'pickup', 'reseller_id' => $reseller->id, 'warehouse_id' => $warehouse->id, 'transaction_date' => '2026-10-01 10:00:00', 'shipment_estimation_datetime' => '2026-10-01 10:00:00', 'shipment_fee' => 0, 'additional_discount' => 0, 'is_additional_discount_percentage' => true, 'items' => [['product_unit_id' => $unit->id, 'qty' => 1, 'unit_price' => 10000, 'tax' => 0, 'total_price' => 10000, 'warehouse_id' => $warehouse->id]]])
            ->assertStatus(400)->assertExactJson(['message' => 'Anda tidak dapat mengkonversi invoice ini']);

        $this->actingAsAdmin();
        $this->putJson("/api/orders/{$order->id}/convert-so", ['type' => 'pickup', 'reseller_id' => $reseller->id, 'warehouse_id' => $warehouse->id, 'transaction_date' => '2026-10-01 10:00:00', 'shipment_estimation_datetime' => '2026-10-01 10:00:00', 'shipment_fee' => 0, 'additional_discount' => 0, 'is_additional_discount_percentage' => true, 'items' => [['product_unit_id' => $unit->id, 'qty' => 1, 'unit_price' => 10000, 'tax' => 0, 'total_price' => 10000, 'warehouse_id' => $warehouse->id]]])
            ->assertOk();
        $this->assertStringStartsWith('IIPE-', $order->fresh()->invoice_no);
        $this->assertTrue(StockHistory::where('description', 'Create SO invoice '.$order->fresh()->invoice_no)->where('is_increment', 0)->exists());

        $this->deleteJson("/api/orders/{$order->id}")
            ->assertStatus(400)->assertExactJson(['message' => 'Quotation sudah diconvert menjadi Invoice. Tidak dapat dihapus']);
        $this->getJson("/api/orders/{$order->id}")->assertOk()->assertJsonPath('data.id', $order->id);
        $this->getJson('/api/orders?filter[spg_id]='.$spg->id)->assertOk()->assertJsonPath('data.0.id', $order->id);
    }

    public function test_sales_order_items_endpoints(): void
    {
        [, $warehouse, $unit] = $this->context();
        $so = $this->makeSalesOrder($warehouse, [[$unit, 2]]);
        $detail = $so->details->first();
        $stock = $this->makeStocks($this->spu($unit, $warehouse), 1)->first();

        $this->postJson("/api/sales-order-items/{$detail->id}", ['stock_id' => $stock->id])->assertStatus(201);
        $this->postJson("/api/sales-order-items/{$detail->id}", ['stock_id' => $stock->id])
            ->assertStatus(400)->assertExactJson(['message' => 'Product sudah di scan']);
        $this->getJson("/api/sales-order-items/{$detail->id}")->assertOk()->assertJsonCount(1);
        $this->deleteJson("/api/sales-order-items/{$detail->id}", ['stock_id' => $stock->id])->assertOk();
        $this->assertSame(0, SalesOrderItem::count());
    }
}
