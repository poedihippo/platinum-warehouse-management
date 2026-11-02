<?php

namespace Tests\Feature\Api;

use App\Models\Stock;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    public function test_full_opname_flow(): void
    {
        $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $spu = $this->spu($this->makeProductUnit(true), $warehouse);
        $group = $this->makeGroupedStock($spu, 2);
        $loose = $this->makeStocks($spu, 2);

        $id = $this->postJson('/api/stock-opnames', ['warehouse_id' => $warehouse->id, 'description' => 'Q4'])
            ->assertStatus(201)->json('data.id');
        $opname = StockOpname::findOrFail($id);
        $detail = $opname->details()->where('stock_product_unit_id', $spu->id)->firstOrFail();
        $base = "/api/stock-opnames/{$id}/details";

        $this->getJson('/api/stock-opnames?filter[description]=Q4')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/stock-opnames/{$id}")->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson($base)->assertOk()->assertJsonFragment(['id' => $detail->id]);
        $this->getJson("{$base}/{$detail->id}")->assertOk()->assertJsonPath('data.id', $detail->id);
        $this->getJson("{$base}/{$detail->id}/items")->assertOk()->assertJsonCount(5);

        $this->putJson("{$base}/{$detail->id}/scan", ['stock_id' => $group->id])
            ->assertStatus(202)->assertExactJson(['message' => 'Stock dan childs berhasil di scan']);
        $this->assertSame(3, StockOpnameItem::where('stock_opname_detail_id', $detail->id)->where('is_scanned', 1)->count());
        $this->putJson("{$base}/{$detail->id}/scan", ['stock_id' => $group->id])
            ->assertStatus(400)->assertExactJson(['message' => 'Stock sudah di scan']);
        $this->putJson("{$base}/999999/scan", ['stock_id' => $group->id])
            ->assertStatus(400)->assertExactJson(['message' => 'Data stock opname tidak cocok']);

        $this->putJson("/api/stock-opnames/{$id}/done", ['is_done' => true])
            ->assertStatus(400)->assertExactJson(['message' => 'Semua data stock opname harus diset selesai']);
        $this->putJson("/api/stock-opnames/{$id}/set-done", ['is_done' => true])
            ->assertStatus(202)->assertExactJson(['message' => 'All data set as Done']);
        $this->putJson("/api/stock-opnames/{$id}/done", ['is_done' => true])
            ->assertStatus(202)->assertExactJson(['message' => 'Data set as Done']);

        $this->assertSoftDeleted('stocks', ['id' => $loose[0]->id]);
        $this->assertSame(3, Stock::where('stock_product_unit_id', $spu->id)->count());
        $this->assertHistory($detail, ['is_increment' => 0]);

        $this->putJson("/api/stock-opnames/{$id}/done", ['is_done' => false])->assertStatus(202);
        $this->assertSame(5, Stock::where('stock_product_unit_id', $spu->id)->count());

        $this->putJson("{$base}/{$detail->id}/done", ['is_done' => false])
            ->assertStatus(202)->assertExactJson(['message' => 'Data set as Pending']);
        $this->deleteJson("{$base}/{$detail->id}/items", ['stock_id' => $loose[1]->id])->assertOk();
        $this->putJson("{$base}/{$detail->id}", ['description' => 'x'])->assertStatus(500);
        $this->putJson("/api/stock-opnames/{$id}", [])->assertStatus(500);

        $this->deleteJson("/api/stock-opnames/{$id}")->assertStatus(500);
        $empty = StockOpname::create(['warehouse_id' => $this->makeWarehouse()->id, 'description' => 'empty']);
        $this->deleteJson("/api/stock-opnames/{$empty->id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
    }
}
