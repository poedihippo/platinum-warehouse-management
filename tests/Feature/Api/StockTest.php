<?php

namespace Tests\Feature\Api;

use App\Models\AdjustmentRequest;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\StockTransfer;
use App\Models\TemporaryStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class StockTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    private function setUpStock(bool $qr = true): array
    {
        $admin = $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $unit = $this->makeProductUnit($qr, ['code' => 'PK'.substr(uniqid(), -4)]);

        return [$admin, $warehouse, $unit, $this->spu($unit, $warehouse)];
    }

    public function test_index_details_and_show(): void
    {
        [, , $unit, $spu] = $this->setUpStock();
        $loose = $this->makeStocks($spu, 2);
        $group = $this->makeGroupedStock($spu, 2);

        $this->getJson("/api/stocks?filter[product_unit_id]={$unit->id}")
            ->assertOk()->assertJsonPath('data.0.id', $spu->id)->assertJsonPath('data.0.qty', 4);

        $this->getJson("/api/stocks/details?filter[stock_product_unit_id]={$spu->id}")->assertOk()->assertJsonCount(4, 'data');
        $this->getJson("/api/stocks/details?filter[parent_id]={$group->id}")->assertOk()->assertJsonCount(2, 'data');

        $this->getJson("/api/stocks/{$group->id}?include=childs")
            ->assertOk()->assertJsonPath('data.id', $group->id)->assertJsonCount(2, 'data.childs');
        $this->getJson('/api/stocks/unknown-id')->assertNotFound();
        $this->assertCount(2, $loose);
    }

    public function test_update_cascades_and_destroy_guards(): void
    {
        [, , , $spu] = $this->setUpStock();
        $group = $this->makeGroupedStock($spu, 2);
        $single = $this->makeStocks($spu, 1)->first();

        $this->putJson("/api/stocks/{$group->id}", ['expired_date' => '2028-02-02'])
            ->assertOk()->assertExactJson(['message' => 'Data updated successfully']);
        $this->assertSame(3, Stock::whereDate('expired_date', '2028-02-02')->count());

        $this->deleteJson("/api/stocks/{$group->id}")->assertStatus(400)->assertExactJson(['message' => 'Tidak dapat menghapus stock parent']);
        $this->deleteJson("/api/stocks/{$single->id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
        $this->assertSoftDeleted('stocks', ['id' => $single->id]);

        $this->deleteJson('/api/stocks/bulk', ['ids' => [$group->id]])->assertOk();
        $this->assertSame(0, Stock::where('stock_product_unit_id', $spu->id)->count());
    }

    public function test_grouping_by_scan_and_by_qty(): void
    {
        [, , $unit, $spu] = $this->setUpStock();
        $stocks = $this->makeStocks($spu, 6);

        $this->postJson('/api/stocks/grouping-by-scan', [
            'stock_product_unit_id' => $spu->id,
            'ids' => $stocks->take(2)->pluck('id')->all(),
            'expired_date' => '2027-03-03',
        ])->assertStatus(201)->assertExactJson(['message' => 'Group stock berhasil dibuat']);

        $first = Stock::whereNull('parent_id')->has('childs')->firstOrFail();
        $this->assertSame(sprintf('%s GR-%s001', $unit->code, date('my')), $first->description);
        $this->assertSame(2, $first->childs()->count());

        $this->postJson('/api/stocks/grouping', ['stock_product_unit_id' => $spu->id, 'total_group' => 5, 'qty' => 5])
            ->assertStatus(400)->assertExactJson(['message' => 'Jumlah total grouping stok melebihi total stok']);

        $this->postJson('/api/stocks/grouping', ['stock_product_unit_id' => $spu->id, 'total_group' => 2, 'qty' => 2])
            ->assertStatus(201)->assertExactJson(['message' => 'Group stock berhasil dibuat']);
        $names = Stock::whereNull('parent_id')->has('childs')->orderBy('description')->pluck('description')->all();
        $this->assertSame([
            sprintf('%s GR-%s001', $unit->code, date('my')),
            sprintf('%s GR-%s002', $unit->code, date('my')),
            sprintf('%s GR-%s003', $unit->code, date('my')),
        ], $names);

        $this->postJson('/api/stocks/grouping', ['stock_product_unit_id' => $spu->id, 'total_group' => 1, 'qty' => 1])
            ->assertStatus(400)->assertExactJson(['message' => 'Stock tidak mencukupi']);
        $this->postJson('/api/stocks/grouping-by-scan', ['stock_product_unit_id' => $spu->id, 'ids' => $stocks->pluck('id')->all()])
            ->assertStatus(422);
    }

    public function test_ungrouping(): void
    {
        [, , , $spu] = $this->setUpStock();
        $group = $this->makeGroupedStock($spu, 2);
        $single = $this->makeStocks($spu, 1)->first();

        $this->postJson("/api/stocks/{$single->id}/ungrouping")
            ->assertStatus(400)->assertExactJson(['message' => 'Stock bukan grouping / tidak memiliki childs']);
        $this->postJson("/api/stocks/{$group->id}/ungrouping")->assertOk()->assertExactJson(['message' => 'success']);

        $this->assertDatabaseMissing('stocks', ['id' => $group->id]);
        $this->assertSame(0, Stock::whereNotNull('parent_id')->count());
    }

    public function test_print_all_and_record(): void
    {
        [, , , $spu] = $this->setUpStock();
        $group = $this->makeGroupedStock($spu, 2);

        $this->getJson("/api/stocks/print-all?filter[parent_id]={$group->id}&print_with_parent=1")->assertOk()->assertJsonCount(3);
        $this->getJson("/api/stocks/print-all?filter[stock_product_unit_id]={$spu->id}")->assertOk()->assertJsonCount(1);

        $this->postJson('/api/stocks/record', ['stock_product_unit_id' => $spu->id, 'stock_ids' => [$group->id]])
            ->assertOk()->assertExactJson(['message' => 'Data berhasil diupdate']);
        $this->assertSame([1, 1, 1], Stock::where('stock_product_unit_id', $spu->id)->pluck('scanned_count')->map(fn ($v) => (int) $v)->all());

        $this->postJson('/api/stocks/record', ['stock_product_unit_id' => $spu->id, 'is_print_all' => true])->assertOk();
        $this->assertSame(6, (int) Stock::where('stock_product_unit_id', $spu->id)->sum('scanned_count'));

        $this->postJson('/api/stocks/record', [])->assertOk()->assertExactJson(['message' => 'Tidak ada data yang diupdate']);
    }

    public function test_bulk_flags_and_printing(): void
    {
        [, , , $spu] = $this->setUpStock();
        $group = $this->makeGroupedStock($spu, 1);
        $ids = [$group->id];
        $all = Stock::pluck('id')->all();

        $this->putJson('/api/stocks/verification-tempel', ['ids' => $ids, 'is_tempel' => true])
            ->assertOk()->assertExactJson(['message' => 'Stock berhasil diupdate Terpasang']);
        $this->assertSame(2, Stock::where('is_tempel', 1)->count());

        $this->postJson('/api/stocks/set-to-printing-queue', ['stocks' => $all, 'printer_id' => 2, 'expired_date' => '2029-01-01'])
            ->assertOk()->assertExactJson(['message' => '2 stocks added to printing queue']);
        $this->assertSame(2, Stock::where('in_printing_queue', 1)->where('printer_id', 2)->count());

        $this->postJson('/api/stocks/set-to-printed', ['stocks' => $ids])->assertOk()->assertExactJson(['message' => '1 stocks set to printed successfully']);
        $this->postJson('/api/stocks/print-verification', ['stocks' => $all, 'is_preview' => true])->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/stocks/print-verification', ['stocks' => $all])->assertOk()->assertExactJson(['message' => '2 stocks scanned successfully']);

        $this->postJson('/api/stocks/add-to-stock', ['ids' => $all, 'is_add' => false])
            ->assertOk()->assertExactJson(['message' => '2 stocks removed successfully']);
        $this->assertSame(0, Stock::where('is_stock', 1)->count());

        $txt = $this->postJson('/api/stocks/export-txt', ['stock_ids' => ['A1', 'B2']])->assertOk();
        $this->assertSame("A1\nB2\n", $txt->getContent());
        $this->assertStringContainsString('attachment; filename="stocks-', $txt->headers->get('content-disposition'));
    }

    public function test_repack_non_qr_and_qr(): void
    {
        [$admin, $warehouse, , $spu] = $this->setUpStock();
        $source = $this->makeStocks($spu, 1)->first();
        $target = $this->spu($this->makeProductUnit(false), $warehouse);

        $this->postJson("/api/stocks/{$source->id}/repack", ['qty' => 4, 'stock_product_unit_id' => $target->id, 'created_at' => '2026-01-02 03:04:05'])
            ->assertOk()->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Berhasil me-repack'));

        $this->assertSoftDeleted('stocks', ['id' => $source->id]);
        $this->assertSame(4, (int) $target->fresh()->qty);
        $adjustment = AdjustmentRequest::where('stock_product_unit_id', $target->id)->firstOrFail();
        $this->assertSame(1, (int) $adjustment->is_approved);
        $this->assertSame($admin->id, (int) $adjustment->approved_by);
        $history = $this->assertHistory($adjustment, ['value' => 4, 'is_increment' => 1]);
        $this->assertSame('2026-01-02 03:04:05', $history->created_at->format('Y-m-d H:i:s'));
        $this->assertHistory($spu, ['value' => 1, 'is_increment' => 0]);

        $qrTarget = $this->spu($this->makeProductUnit(true), $warehouse);
        $other = $this->makeStocks($spu, 1)->first();
        $this->postJson("/api/stocks/{$other->id}/repack", ['qty' => 3, 'stock_product_unit_id' => $qrTarget->id])->assertOk();
        $this->assertSame(3, Stock::where('stock_product_unit_id', $qrTarget->id)->count());

        $group = $this->makeGroupedStock($spu, 1);
        $this->postJson("/api/stocks/{$group->id}/repack", ['qty' => 1, 'stock_product_unit_id' => $target->id])
            ->assertStatus(400)->assertExactJson(['message' => 'Tidak dapat me-repack stock parent']);
    }

    public function test_transfer_qr_moves_group_and_writes_histories(): void
    {
        [$admin, $warehouse, $unit, $spu] = $this->setUpStock();
        $destination = $this->makeWarehouse(['name' => 'Gudang Tujuan']);
        $toSpu = $this->spu($unit, $destination);
        $group = $this->makeGroupedStock($spu, 2);

        $this->postJson('/api/stocks/transfer', [
            'stock_product_unit_id' => $spu->id, 'to_warehouse_id' => $destination->id, 'stock_ids' => [$group->id], 'description' => 'Rebalance',
        ])->assertStatus(201)->assertExactJson(['message' => 'Transfer stock berhasil']);

        $this->assertSame(3, Stock::where('stock_product_unit_id', $toSpu->id)->count());
        $transfer = StockTransfer::firstOrFail();
        $this->assertSame(2, (int) $transfer->qty);
        $this->assertHistory($transfer, ['stock_product_unit_id' => $spu->id, 'is_increment' => 0, 'value' => 2, 'description' => 'Transfer stock ke Gudang Tujuan - Rebalance', 'user_id' => $admin->id]);
        $this->assertHistory($transfer, ['stock_product_unit_id' => $toSpu->id, 'is_increment' => 1, 'description' => 'Transfer stock dari '.$warehouse->name.' - Rebalance']);

        $this->postJson('/api/stocks/transfer', ['stock_product_unit_id' => $spu->id, 'to_warehouse_id' => $destination->id, 'qty' => 1])
            ->assertStatus(422)->assertJsonValidationErrors(['stock_ids']);
    }

    public function test_stock_history_and_temporary_stock(): void
    {
        [$admin, , , $spu] = $this->setUpStock();
        $stock = $this->makeStocks($spu, 1)->first();
        StockHistory::create(['user_id' => $admin->id, 'stock_product_unit_id' => $spu->id, 'value' => 1, 'is_increment' => 1, 'description' => 'Manual', 'model_type' => $spu->getMorphClass(), 'model_id' => $spu->id]);

        $this->getJson("/api/stock-histories?filter[stock_product_unit_id]={$spu->id}&filter[description]=Manu")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.description', 'Manual');

        $this->postJson('/api/temporary-stocks', ['id' => $stock->id])
            ->assertOk()->assertExactJson(['message' => $stock->id.' stock scanned successfully']);
        $this->assertSame($admin->id, (int) TemporaryStock::firstOrFail()->created_by_id);
        $this->getJson('/api/temporary-stocks')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/temporary-stocks', ['id' => 'nope'])->assertStatus(422);
    }
}
