<?php

namespace Tests\Feature\Enums;

use App\Models\ProductBrand;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use App\Pipes\Order\CalculateVoucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class EnumBehaviorTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    public function test_strict_enum_rule_rejects_unknown_value_with_the_legacy_message(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/products', [
            'name' => 'Product',
            'company' => 'xyz',
            'product_category_id' => $this->makeProduct()->product_category_id,
            'product_brand_id' => $this->makeProduct()->product_brand_id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.company.0', 'The value you have entered is invalid.');
    }

    public function test_strict_enum_rule_rejects_a_value_with_different_case(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/products', ['name' => 'Product', 'company' => 'PAS'])
            ->assertStatus(422)
            ->assertJsonPath('errors.company.0', 'The value you have entered is invalid.');
    }

    public function test_strict_enum_rule_accepts_a_known_value(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/products', ['company' => 'pa']);

        $response->assertStatus(422);
        $this->assertArrayNotHasKey('company', $response->json('errors'));
    }

    public function test_loose_enum_rule_rejects_unknown_value_with_the_legacy_message(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/users', ['name' => 'Someone', 'type' => 'boss', 'password' => 'secret'])
            ->assertStatus(422)
            ->assertJsonPath('errors.type.0', 'The value you have entered is invalid.');
    }

    public function test_required_if_rule_built_from_the_reseller_type_still_applies(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/users', ['name' => 'Reseller', 'type' => 'reseller'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code'])
            ->assertJsonMissingValidationErrors(['password', 'type']);
    }

    public function test_cast_enum_attributes_serialize_as_their_value(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct(['company' => 'pa']);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.company', 'pa');
    }

    public function test_user_resource_exposes_the_type_description(): void
    {
        $this->actingAsAdmin();
        $customerEvent = $this->makeUser(['type' => 'customer_event']);
        $spg = $this->makeUser(['type' => 'spg']);

        $this->getJson("/api/users/{$customerEvent->id}")->assertOk()->assertJsonPath('data.type', 'Customer event');
        $this->getJson("/api/users/{$spg->id}")->assertOk()->assertJsonPath('data.type', 'Spg');
    }

    public function test_creating_a_reseller_creates_one_discount_per_brand(): void
    {
        ProductBrand::create(['name' => 'Brand A']);
        ProductBrand::create(['name' => 'Brand B']);

        $reseller = $this->makeUser(['type' => 'reseller']);
        $customer = $this->makeUser(['type' => 'customer']);

        $this->assertSame(2, $reseller->userDiscounts()->count());
        $this->assertSame(0, $customer->userDiscounts()->count());
    }

    public function test_user_discount_endpoints_require_a_reseller(): void
    {
        $this->actingAsAdmin();
        $customer = $this->makeUser(['type' => 'customer']);
        $brand = ProductBrand::create(['name' => 'Brand '.uniqid()]);

        $this->postJson("/api/users/{$customer->id}/discounts", [
            'product_brand_id' => $brand->id,
            'value' => 5,
            'is_percentage' => true,
        ])
            ->assertStatus(400)
            ->assertExactJson(['message' => 'Tipe user harus reseller']);
    }

    public function test_spg_users_only_see_their_own_sales_orders(): void
    {
        $warehouse = $this->makeWarehouse();
        $spg = $this->makeUser(['type' => 'spg']);
        $otherSpg = $this->makeUser(['type' => 'spg']);
        $admin = $this->makeUser(['type' => 'admin']);

        $mine = $this->salesOrder($warehouse, $admin, ['spg_id' => $spg->id]);
        $this->salesOrder($warehouse, $admin, ['spg_id' => $otherSpg->id]);

        $this->assertSame([$mine->id], SalesOrder::tenanted($spg)->pluck('id')->all());
        $this->assertSame(2, SalesOrder::tenanted($admin)->count());
    }

    public function test_setting_values_are_cast_by_key(): void
    {
        Setting::create(['key' => 'so_number', 'value' => '007']);
        Setting::create(['key' => 'tax_value', 'value' => '11']);
        Setting::create(['key' => 'bank_name', 'value' => 'BCA']);

        Sanctum::actingAs($this->actingAsAdmin(), ['*']);

        $response = $this->getJson('/api/settings')->assertOk();

        $values = collect($response->json('data'))->pluck('value', 'key')->all();
        $this->assertSame('007', $values['so_number']);
        $this->assertSame(11, $values['tax_value']);
        $this->assertSame('BCA', $values['bank_name']);
    }

    public function test_import_sample_uses_the_enum_description_as_file_name(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/api/exports/sample/voucher')->assertOk();
        $this->assertStringContainsString('Voucher.csv', $response->headers->get('content-disposition'));

        $this->get('/api/exports/sample/VOUCHER')->assertOk();
        $this->get('/api/exports/sample/unknown')->assertStatus(302);
    }

    public function test_nominal_and_percentage_vouchers_are_calculated_differently(): void
    {
        $nominal = VoucherCategory::create(['name' => 'Nominal', 'discount_type' => 'nominal', 'discount_amount' => 15000]);
        $percentage = VoucherCategory::create(['name' => 'Percent', 'discount_type' => 'percentage', 'discount_amount' => 10]);
        Voucher::create(['voucher_category_id' => $nominal->id, 'code' => 'NOM']);
        Voucher::create(['voucher_category_id' => $percentage->id, 'code' => 'PCT']);

        $salesOrder = SalesOrder::make(['raw_source' => ['voucher_codes' => ['NOM', 'PCT']]]);
        $salesOrder->price = 100000;

        $result = (new CalculateVoucher)->handle($salesOrder, fn ($order) => $order);

        $this->assertSame(76500, (int) $result->price);
        $this->assertSame(23500, $result->total_voucher);
    }

    private function salesOrder($warehouse, User $user, array $attributes = []): SalesOrder
    {
        return SalesOrder::create(array_merge([
            'is_invoice' => true,
            'invoice_no' => 'INV/'.uniqid(),
            'type' => 'default',
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => now(),
            'shipment_estimation_datetime' => now(),
            'price' => 0,
            'additional_discount' => 0,
            'auto_discount' => 0,
            'shipment_fee' => 0,
        ], $attributes));
    }
}
