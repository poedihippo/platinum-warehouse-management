<?php

namespace Tests\Feature\Api;

use App\Models\ProductUnitBlacklist;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderDetail;
use App\Models\Stock;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class ReceiveOrderTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    private function xml(string $invoiceNo, string $vendor, string $warehouse, array $lines): UploadedFile
    {
        $items = collect($lines)->map(fn ($l) => "<ITEMLINE><ITEMNO>{$l[0]}</ITEMNO><QUANTITY>{$l[1]}</QUANTITY><ITEMUNIT>PCS</ITEMUNIT><BRUTOUNITPRICE>1000</BRUTOUNITPRICE></ITEMLINE>")->implode('');
        $xml = "<?xml version=\"1.0\"?><NMEXML><TRANSACTIONS><RECIEVEITEM><INVOICENO>{$invoiceNo}</INVOICENO><VENDORID>{$vendor}</VENDORID><WAREHOUSEID>{$warehouse}</WAREHOUSEID><INVOICEDATE>2026-10-01</INVOICEDATE><INVOICEAMOUNT>5000</INVOICEAMOUNT><PURCHASEORDERNO>PO-1</PURCHASEORDERNO><SEQUENCENO>7</SEQUENCENO>{$items}</RECIEVEITEM></TRANSACTIONS></NMEXML>";

        return UploadedFile::fake()->createWithContent('ro.xml', $xml);
    }

    private function rollBackTransactionsLeftOpenByTheRequest(): void
    {
        while (DB::transactionLevel() > 1) {
            DB::rollBack();
        }
    }

    private function context(): array
    {
        $admin = $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse(['code' => 'GDG1']);
        $supplier = Supplier::create(['code' => 'SUP1', 'name' => 'Supplier', 'email' => 's@test.com', 'phone' => '1', 'address' => 'x']);
        $qrUnit = $this->makeProductUnit(true, ['code' => 'QR-1']);
        $plainUnit = $this->makeProductUnit(false, ['code' => 'NQ-1']);

        return compact('admin', 'warehouse', 'supplier', 'qrUnit', 'plainUnit');
    }

    private function store(UploadedFile $file)
    {
        return $this->post('/api/receive-orders', ['receive_datetime' => '2026-10-02 08:00:00', 'file' => $file], ['Accept' => 'application/json']);
    }

    public function test_store_from_xml_skips_blacklist_and_guards(): void
    {
        $c = $this->context();
        $blocked = $this->makeProductUnit(false, ['code' => 'BL-1']);
        ProductUnitBlacklist::create(['product_unit_id' => $blocked->id]);

        $response = $this->store($this->xml('INV-1', 'SUP1', 'GDG1', [['QR-1', 4], ['NQ-1', 6], ['BL-1', 1]]))
            ->assertStatus(201)
            ->assertJsonPath('data.invoice_no', 'INV-1');

        $ro = ReceiveOrder::findOrFail($response->json('data.id'));
        $this->assertSame($c['admin']->id, $ro->user_id);
        $this->assertSame($c['warehouse']->id, $ro->warehouse_id);
        $this->assertEqualsCanonicalizing([$c['qrUnit']->id, $c['plainUnit']->id], $ro->details->pluck('product_unit_id')->all());

        $this->store($this->xml('INV-1', 'SUP1', 'GDG1', [['QR-1', 1]]))
            ->assertStatus(400)->assertExactJson(['message' => 'Invoice number sudah digunakan']);
        $this->store($this->xml('INV-2', 'SUP1', 'GDG1', [['UNKNOWN', 1]]))
            ->assertStatus(400)->assertExactJson(['message' => 'Product UNKNOWN Tidak ditemukan on system. Please add first']);
        $this->rollBackTransactionsLeftOpenByTheRequest();
        $this->assertFalse(ReceiveOrder::where('invoice_no', 'INV-2')->exists());
        $this->store($this->xml('INV-3', 'NOPE', 'GDG1', [['QR-1', 1]]))->assertNotFound();

        $this->store($this->xml('INV-4', 'SUP1', 'GDG1', [['NQ-1', 2]]))->assertStatus(201);
        $this->assertSame(1, ReceiveOrder::where('invoice_no', 'INV-4')->firstOrFail()->details()->count());
    }

    public function test_index_show_details_and_verify(): void
    {
        $c = $this->context();
        $id = $this->store($this->xml('INV-9', 'SUP1', 'GDG1', [['QR-1', 4]]))->json('data.id');
        $detail = ReceiveOrderDetail::where('receive_order_id', $id)->firstOrFail();

        $this->getJson('/api/receive-orders?filter[invoice_no]=INV-9&include=details')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/receive-orders/{$id}")->assertOk()->assertJsonPath('data.details_count', 1);
        $this->getJson("/api/receive-orders/{$id}/details")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/receive-orders/{$id}/details/{$detail->id}")->assertOk()->assertJsonPath('data.id', $detail->id);

        $this->postJson("/api/receive-orders/{$id}/details", ['product_unit_id' => $c['plainUnit']->id, 'qty' => 2, 'item_unit' => 'PCS', 'bruto_unit_price' => 5])
            ->assertStatus(201)->assertJsonPath('data.qty', 2);

        $this->putJson("/api/receive-orders/{$id}/details/{$detail->id}/verify", ['is_verified' => true])->assertStatus(202);
        $this->putJson("/api/receive-orders/{$id}/details/{$detail->id}/verify", ['is_verified' => true])
            ->assertStatus(400)->assertExactJson(['message' => 'Tidak dapat memperbarui dengan status yang sama']);

        $this->deleteJson("/api/receive-orders/{$id}/details/{$detail->id}")
            ->assertOk()->assertExactJson(['message' => 'Data harus tidak diverifikasi']);
        $this->deleteJson("/api/receive-orders/{$id}")
            ->assertOk()->assertExactJson(['message' => 'Semua receive order harus unverified']);
    }

    public function test_known_quirk_detail_update_request_calls_details_on_a_route_string(): void
    {
        $this->context();
        $id = $this->store($this->xml('INV-5', 'SUP1', 'GDG1', [['QR-1', 4]]))->json('data.id');
        $detail = ReceiveOrderDetail::where('receive_order_id', $id)->firstOrFail();

        $this->putJson("/api/receive-orders/{$id}/details/{$detail->id}", ['adjust_qty' => 2])->assertStatus(500);
    }

    public function test_update_validates_and_404s_before_the_dd_call(): void
    {
        $this->context();

        $this->putJson('/api/receive-orders/999999', [])->assertStatus(422);
        $this->putJson('/api/receive-orders/999999', ['name' => 'x'])->assertNotFound();
    }

    public function test_done_creates_stock_and_guards(): void
    {
        $c = $this->context();
        $id = $this->store($this->xml('INV-6', 'SUP1', 'GDG1', [['QR-1', 4], ['NQ-1', 6]]))->json('data.id');
        $ro = ReceiveOrder::findOrFail($id);

        $this->putJson("/api/receive-orders/{$id}/done", ['is_done' => true])
            ->assertStatus(400)->assertExactJson(['message' => 'Semua receive order harus diverifikasi']);

        $ro->details()->update(['is_verified' => 1]);
        $this->putJson("/api/receive-orders/{$id}/done", ['is_done' => true])
            ->assertStatus(400)->assertExactJson(['message' => 'Semua qty detail receive order harus di adjust']);

        $ro->details()->where('product_unit_id', $c['qrUnit']->id)->update(['adjust_qty' => 3]);
        $ro->details()->where('product_unit_id', $c['plainUnit']->id)->update(['adjust_qty' => 5]);
        $this->putJson("/api/receive-orders/{$id}", [])->assertStatus(422);
        $this->putJson("/api/receive-orders/{$id}/done", ['is_done' => true])
            ->assertStatus(202)->assertExactJson(['message' => 'Receive order set as Done']);

        $qrSpu = $this->spu($c['qrUnit'], $c['warehouse']);
        $this->assertSame(3, Stock::where('stock_product_unit_id', $qrSpu->id)->where('receive_order_id', $id)->count());
        $this->assertSame(5, (int) $this->spu($c['plainUnit'], $c['warehouse'])->qty);
        $qrDetail = $ro->details()->where('product_unit_id', $c['qrUnit']->id)->first();
        $this->assertHistory($qrDetail, ['value' => 3, 'is_increment' => 1, 'description' => 'INV-6', 'user_id' => $c['admin']->id]);

        $this->putJson("/api/receive-orders/{$id}/done", ['is_done' => false])->assertStatus(500);
    }

    public function test_known_quirk_grouping_from_receive_order_page_fails_before_writing(): void
    {
        $c = $this->context();
        $id = $this->store($this->xml('INV-7', 'SUP1', 'GDG1', [['QR-1', 4]]))->json('data.id');
        $detail = ReceiveOrderDetail::where('receive_order_id', $id)->firstOrFail();
        $spu = $this->spu($c['qrUnit'], $c['warehouse']);
        $this->makeStocks($spu, 4, ['receive_order_id' => $id, 'receive_order_detail_id' => $detail->id]);

        $this->postJson('/api/stocks/grouping', ['receive_order_detail_id' => $detail->id, 'total_group' => 1, 'qty' => 2])->assertStatus(500);
        $this->assertSame(0, Stock::whereNotNull('parent_id')->count());
        $this->assertSame(4, Stock::count());
    }
}
