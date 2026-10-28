<?php

namespace Tests\Feature\Api;

use App\Enums\CompanyEnum;
use App\Enums\UserType;
use App\Http\Requests\Api\DeliveryOrderStoreRequest;
use App\Http\Requests\Api\DeliveryOrderUpdateRequest;
use App\Http\Requests\Api\SalesOrderMergeRequest;
use App\Http\Requests\Api\SalesOrderStoreRequest;
use App\Http\Requests\Api\SalesOrderUpdateRequest;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualInvoiceNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.enable_document_counter' => false]);
    }

    /**
     * Run a form request's rules without going through the HTTP layer.
     */
    private function validate(string $requestClass, array $payload): Validator
    {
        $request = $requestClass::create('/api/test', 'POST', $payload);

        return ValidatorFactory::make($payload, $request->rules(), $request->messages());
    }

    private function salesOrderPayload(array $overrides = []): array
    {
        return array_merge([
            'company' => CompanyEnum::PAS()->value,
            'warehouse_id' => 1,
            'shipment_fee' => 0,
            'additional_discount' => 0,
            'items' => [
                [
                    'product_unit_id' => 1,
                    'qty' => 1,
                    'unit_price' => 1000,
                    'discount' => 0,
                    'tax' => false,
                    'total_price' => 1000,
                    'warehouse_id' => 1,
                ],
            ],
        ], $overrides);
    }

    private function deliveryOrderPayload(array $overrides = []): array
    {
        return array_merge([
            'company' => CompanyEnum::PAS()->value,
            'reseller_id' => 1,
            'warehouse_id' => 1,
            'transaction_date' => '2026-10-16 10:00:00',
            'shipment_estimation_datetime' => '2026-10-16 10:00:00',
        ], $overrides);
    }

    private function mergePayload(array $overrides = []): array
    {
        return array_merge([
            'sales_order_ids' => [1, 2],
            'transaction_date' => '2026-10-16 10:00:00',
        ], $overrides);
    }

    /**
     * Authenticate as a warehouse staff user with the given permissions.
     */
    private function actingAsWarehouseUser(array $permissions = []): User
    {
        $user = User::create([
            'name' => 'Numbering User',
            'email' => 'numbering-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'type' => UserType::Customer,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        Sanctum::actingAs($user, ['warehouse']);

        return $user;
    }

    public function test_sales_order_requires_a_manual_number_when_the_counter_is_disabled(): void
    {
        $errors = $this->validate(SalesOrderStoreRequest::class, $this->salesOrderPayload())->errors();

        $this->assertTrue($errors->has('invoice_no'));
    }

    public function test_sales_order_number_is_optional_when_the_counter_is_enabled(): void
    {
        config(['app.enable_document_counter' => true]);

        $errors = $this->validate(SalesOrderStoreRequest::class, $this->salesOrderPayload())->errors();

        $this->assertFalse($errors->has('invoice_no'));
    }

    public function test_delivery_order_requires_a_manual_number_when_the_counter_is_disabled(): void
    {
        $errors = $this->validate(DeliveryOrderStoreRequest::class, $this->deliveryOrderPayload())->errors();

        $this->assertTrue($errors->has('invoice_no'));
    }

    public function test_delivery_order_number_is_optional_when_the_counter_is_enabled(): void
    {
        config(['app.enable_document_counter' => true]);

        $errors = $this->validate(DeliveryOrderStoreRequest::class, $this->deliveryOrderPayload())->errors();

        $this->assertFalse($errors->has('invoice_no'));
    }

    public function test_merge_requires_a_manual_number_for_the_sales_order_and_the_delivery_order(): void
    {
        $errors = $this->validate(SalesOrderMergeRequest::class, $this->mergePayload())->errors();

        $this->assertTrue($errors->has('invoice_no'));
        $this->assertTrue($errors->has('delivery_order_invoice_no'));
    }

    public function test_merge_rejects_a_delivery_order_number_equal_to_the_sales_order_number(): void
    {
        $errors = $this->validate(SalesOrderMergeRequest::class, $this->mergePayload([
            'invoice_no' => 'PAS/SO/10/26/99',
            'delivery_order_invoice_no' => 'PAS/SO/10/26/99',
        ]))->errors();

        $this->assertSame(
            ['Nomor DO tidak boleh sama dengan nomor SO'],
            $errors->get('delivery_order_invoice_no')
        );
    }

    public function test_merge_accepts_distinct_sales_order_and_delivery_order_numbers(): void
    {
        $errors = $this->validate(SalesOrderMergeRequest::class, $this->mergePayload([
            'invoice_no' => 'PAS/SO/10/26/99',
            'delivery_order_invoice_no' => 'PAS/DO/10/26/99',
        ]))->errors();

        $this->assertFalse($errors->has('invoice_no'));
        $this->assertFalse($errors->has('delivery_order_invoice_no'));
    }

    public function test_merge_numbers_are_optional_and_ignored_when_the_counter_is_enabled(): void
    {
        config(['app.enable_document_counter' => true]);

        $errors = $this->validate(SalesOrderMergeRequest::class, $this->mergePayload([
            'invoice_no' => 'SAME/1',
            'delivery_order_invoice_no' => 'SAME/1',
        ]))->errors();

        $this->assertFalse($errors->has('invoice_no'));
        $this->assertFalse($errors->has('delivery_order_invoice_no'));
    }

    public function test_sales_order_update_requires_a_manual_number_when_the_counter_is_disabled(): void
    {
        $errors = $this->validate(SalesOrderUpdateRequest::class, $this->salesOrderPayload())->errors();

        $this->assertTrue($errors->has('invoice_no'));
    }

    public function test_delivery_order_update_requires_a_manual_number_when_the_counter_is_disabled(): void
    {
        $errors = $this->validate(DeliveryOrderUpdateRequest::class, $this->deliveryOrderPayload())->errors();

        $this->assertTrue($errors->has('invoice_no'));
    }

    public function test_sales_order_update_number_is_optional_when_the_counter_is_enabled(): void
    {
        config(['app.enable_document_counter' => true]);

        $errors = $this->validate(SalesOrderUpdateRequest::class, $this->salesOrderPayload())->errors();

        $this->assertFalse($errors->has('invoice_no'));
    }

    public function test_sales_order_creation_is_rejected_without_a_manual_number_over_http(): void
    {
        $this->actingAsWarehouseUser();

        $this->postJson('/api/sales-orders', $this->salesOrderPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('invoice_no');
    }

    public function test_blank_manual_number_is_rejected_over_http(): void
    {
        $this->actingAsWarehouseUser();

        $this->postJson('/api/sales-orders', $this->salesOrderPayload(['invoice_no' => '   ']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('invoice_no');
    }

    public function test_delivery_order_creation_is_rejected_without_a_manual_number_over_http(): void
    {
        $this->actingAsWarehouseUser(['delivery_order_create']);

        $this->postJson('/api/delivery-orders', $this->deliveryOrderPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('invoice_no');
    }
}
