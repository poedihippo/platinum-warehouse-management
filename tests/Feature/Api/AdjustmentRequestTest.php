<?php

namespace Tests\Feature\Api;

use App\Models\AdjustmentRequest;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class AdjustmentRequestTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    private function context(bool $qr, bool $autoStock = true): array
    {
        $admin = $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $unit = $this->makeProductUnit($qr, ['is_auto_stock' => $autoStock]);

        return [$admin, $this->spu($unit, $warehouse)];
    }

    private function adjustment($spu, array $attributes = []): AdjustmentRequest
    {
        return AdjustmentRequest::create(array_merge([
            'stock_product_unit_id' => $spu->id,
            'value' => 3,
            'expired_date' => '2027-05-01',
            'is_increment' => true,
            'description' => 'Opname fix',
        ], $attributes));
    }

    public function test_crud_and_guards(): void
    {
        [$admin, $spu] = $this->context(true);

        $created = $this->postJson('/api/adjustment-requests', [
            'stock_product_unit_id' => $spu->id, 'value' => 2, 'expired_date' => '2027-01-01', 'description' => 'x',
        ])->assertStatus(201)->assertJsonPath('data.value', 2);
        $id = $created->json('data.id');
        $this->assertSame($admin->id, AdjustmentRequest::find($id)->user_id);

        $this->getJson('/api/adjustment-requests?filter[stock_product_unit_id]='.$spu->id.'&sort=-id')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/adjustment-requests/{$id}")->assertOk()->assertJsonPath('data.id', $id);
        $this->putJson("/api/adjustment-requests/{$id}", [
            'stock_product_unit_id' => $spu->id, 'value' => 5, 'expired_date' => '2027-01-01',
        ])->assertStatus(202)->assertJsonPath('data.value', 5);

        AdjustmentRequest::whereKey($id)->update(['is_approved' => 1]);
        $this->putJson("/api/adjustment-requests/{$id}", ['stock_product_unit_id' => $spu->id, 'value' => 6, 'expired_date' => '2027-01-01'])
            ->assertStatus(400)->assertExactJson(['message' => 'Tidak dapat update data jika sudah di approved']);
        $this->deleteJson("/api/adjustment-requests/{$id}")
            ->assertStatus(400)->assertExactJson(['message' => 'Tidak dapat menghapus data jika sudah di approved']);

        $other = $this->adjustment($spu);
        $this->deleteJson("/api/adjustment-requests/{$other->id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
        $this->assertSoftDeleted('adjustment_requests', ['id' => $other->id]);
    }

    public function test_approve_increment_qr_creates_stocks_and_history(): void
    {
        [$admin, $spu] = $this->context(true, false);
        $adjustment = $this->adjustment($spu);

        $this->putJson("/api/adjustment-requests/{$adjustment->id}/approve", ['is_approved' => true, 'reason' => 'ok'])
            ->assertOk()->assertExactJson(['message' => 'Data approved successfully']);

        $stocks = Stock::where('adjustment_request_id', $adjustment->id)->get();
        $this->assertCount(3, $stocks);
        $this->assertSame([0], $stocks->pluck('is_stock')->map(fn ($v) => (int) $v)->unique()->values()->all());
        $this->assertStringStartsWith('2027-05-01', (string) $stocks->first()->expired_date);
        $this->assertHistory($adjustment, [
            'user_id' => $admin->id, 'stock_product_unit_id' => $spu->id, 'value' => 3, 'is_increment' => 1,
            'description' => 'Adjustment request (Penambahan) - Opname fix', 'ip' => '127.0.0.1', 'agent' => 'Symfony',
        ]);
        $adjustment->refresh();
        $this->assertSame('ok', $adjustment->reason);
        $this->assertSame($admin->id, (int) $adjustment->approved_by);

        $this->putJson("/api/adjustment-requests/{$adjustment->id}/approve", ['is_approved' => true])
            ->assertNotFound()->assertExactJson(['message' => 'Adjustment request sudah di approve. Tidak dapat di edit!']);
    }

    public function test_approve_increment_and_decrement_non_qr_change_qty(): void
    {
        [, $spu] = $this->context(false);
        $spu->update(['qty' => 10]);

        $inc = $this->adjustment($spu, ['value' => 4]);
        $this->putJson("/api/adjustment-requests/{$inc->id}/approve", ['is_approved' => 1])->assertOk();
        $this->assertSame(14, (int) $spu->fresh()->qty);

        $dec = $this->adjustment($spu, ['value' => 6, 'is_increment' => false]);
        $this->putJson("/api/adjustment-requests/{$dec->id}/approve", ['is_approved' => 1])->assertOk();
        $this->assertSame(8, (int) $spu->fresh()->qty);
        $this->assertHistory($dec, ['value' => 6, 'is_increment' => 0, 'description' => 'Adjustment request (Pengurangan) - Opname fix']);
    }

    public function test_approve_decrement_qr_soft_deletes_available_stocks(): void
    {
        [, $spu] = $this->context(true);
        $this->makeStocks($spu, 5);

        $dec = $this->adjustment($spu, ['value' => 2, 'is_increment' => false]);
        $this->putJson("/api/adjustment-requests/{$dec->id}/approve", ['is_approved' => true])->assertOk();

        $this->assertSame(3, Stock::where('stock_product_unit_id', $spu->id)->count());
        $this->assertSame(2, Stock::onlyTrashed()->where('stock_product_unit_id', $spu->id)->count());
    }

    public function test_reject_writes_no_history(): void
    {
        [, $spu] = $this->context(true);
        $adjustment = $this->adjustment($spu);

        $this->putJson("/api/adjustment-requests/{$adjustment->id}/approve", ['is_approved' => 0, 'reason' => 'no'])
            ->assertOk()->assertExactJson(['message' => 'Data rejected successfully']);

        $this->assertCount(0, $this->histories($adjustment));
        $this->assertSame(0, Stock::where('adjustment_request_id', $adjustment->id)->count());
        $this->putJson("/api/adjustment-requests/{$adjustment->id}/approve", ['is_approved' => 1])
            ->assertNotFound()->assertExactJson(['message' => 'Adjustment request sudah di reject. Tidak dapat di edit!']);
    }
}
