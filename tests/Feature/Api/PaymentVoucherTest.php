<?php

namespace Tests\Feature\Api;

use App\Models\Payment;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use App\Models\VoucherGenerateBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class PaymentVoucherTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    public function test_payment_crud_soft_delete_and_full_payment(): void
    {
        $admin = $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $so = $this->makeSalesOrder($warehouse, [[$this->makeProductUnit(), 1]], ['price' => 75000]);

        $id = $this->postJson('/api/payments', ['sales_order_id' => $so->id, 'type' => 'cash', 'is_full_payment' => true])
            ->assertStatus(201)->assertJsonPath('data.amount', 75000)->json('data.id');
        $this->assertSame($admin->id, Payment::find($id)->user_id);

        $this->getJson("/api/payments?filter[sales_order_id]={$so->id}&include=user")->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/payments/{$id}")->assertOk()->assertJsonPath('data.sales_order.id', $so->id);
        $this->putJson("/api/payments/{$id}", ['sales_order_id' => $so->id, 'type' => 'transfer', 'amount' => 1000])
            ->assertStatus(202)->assertJsonPath('data.amount', 1000);

        $this->deleteJson("/api/payments/{$id}")->assertOk();
        $this->assertSame($admin->id, (int) Payment::withTrashed()->find($id)->deleted_by);
        $this->putJson("/api/payments/{$id}/restore")->assertOk()->assertJsonPath('data.id', $id);
        $this->assertNull(Payment::find($id)->deleted_by);
        $this->deleteJson("/api/payments/{$id}/force-delete")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
        $this->assertNull(Payment::withTrashed()->find($id));
    }

    public function test_voucher_category_crud(): void
    {
        $this->actingAsAdmin();

        $id = $this->postJson('/api/voucher-categories', ['name' => 'Promo', 'discount_type' => 'nominal', 'discount_amount' => 5000])
            ->assertStatus(201)->json('data.id');
        $this->getJson('/api/voucher-categories?filter[name]=Pro')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->putJson("/api/voucher-categories/{$id}", ['name' => 'Promo 2', 'discount_type' => 'percentage', 'discount_amount' => 5])
            ->assertStatus(202)->assertJsonPath('data.name', 'Promo 2');
        $this->deleteJson("/api/voucher-categories/{$id}")->assertOk();
        $this->putJson("/api/voucher-categories/{$id}/restore")->assertOk()->assertJsonPath('data.id', $id);
        $this->deleteJson("/api/voucher-categories/{$id}/force-delete")->assertOk();
        $this->assertNull(VoucherCategory::withTrashed()->find($id));
    }

    public function test_vouchers_and_generate_batches(): void
    {
        $this->actingAsAdmin();
        $category = VoucherCategory::create(['name' => 'C', 'discount_type' => 'nominal', 'discount_amount' => 1000]);

        $this->postJson('/api/vouchers', ['voucher_category_id' => $category->id])
            ->assertStatus(201)->assertExactJson(['message' => 'Data created successfully']);
        $voucher = Voucher::firstOrFail();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $voucher->code);

        $this->getJson('/api/vouchers?include=category')->assertOk()->assertJsonPath('data.0.id', $voucher->id);
        $this->getJson("/api/vouchers/{$voucher->id}")->assertOk()->assertJsonPath('data.id', $voucher->id);

        $warehouse = $this->makeWarehouse();
        $so = $this->makeSalesOrder($warehouse, [], ['raw_source' => ['voucher_codes' => [$voucher->code]]]);
        $so->vouchers()->attach($voucher->id, ['discount_amount' => 1000]);

        $this->putJson("/api/vouchers/{$voucher->id}", ['voucher_category_id' => $category->id, 'code' => 'NEWCODE1'])
            ->assertOk()->assertExactJson(['message' => 'Data updated successfully']);
        $this->assertSame(['NEWCODE1'], $so->fresh()->raw_source['voucher_codes']);

        $this->deleteJson("/api/vouchers/{$voucher->id}")
            ->assertStatus(422)->assertExactJson(['message' => 'Cannot delete used voucher. Voucher already used!']);

        $free = Voucher::create(['voucher_category_id' => $category->id, 'code' => 'FREE1']);
        $this->deleteJson("/api/vouchers/{$free->id}")->assertOk();
        $this->putJson("/api/vouchers/{$free->id}/restore")->assertOk()->assertExactJson(['message' => 'Data updated successfully']);
        $this->deleteJson("/api/vouchers/{$free->id}/force-delete")->assertOk();

        $this->postJson('/api/vouchers/generate-batches', ['voucher_category_id' => $category->id, 'value' => 3])
            ->assertStatus(201)->assertExactJson(['message' => 'Data created successfully']);
        $batch = VoucherGenerateBatch::firstOrFail();
        $this->assertSame([$batch->id.'-0', $batch->id.'-1', $batch->id.'-2'], $batch->vouchers()->orderBy('id')->pluck('code')->all());
        $this->getJson('/api/vouchers/generate-batches?include=user')->assertOk()->assertJsonPath('data.0.id', $batch->id);
        $this->getJson("/api/vouchers/generate-batches/{$batch->id}")->assertOk()->assertJsonPath('data.vouchers_count', 3);
        $this->putJson("/api/vouchers/generate-batches/{$batch->id}", ['voucher_category_id' => $category->id, 'value' => 1, 'description' => 'x'])->assertOk();
        $this->deleteJson("/api/vouchers/generate-batches/{$batch->id}")->assertOk();
    }
}
