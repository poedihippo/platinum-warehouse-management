<?php

namespace Tests\Feature\Api;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use App\Enums\UserType;
use App\Models\DocumentCounter;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\StockProductUnit;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentCounterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'document_counter_read', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'document_counter_edit', 'guard_name' => 'web']);
    }

    private function actingAsUser(array $permissions): User
    {
        $user = User::create([
            'name' => 'Counter User',
            'email' => 'counter-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'type' => UserType::Customer,
        ]);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        Sanctum::actingAs($user, ['warehouse']);

        return $user;
    }

    /**
     * Index every row of a response by "company-document_type" for easy assertions.
     */
    private function rows(TestResponse $response): array
    {
        $rows = [];

        foreach ($response->json('data') as $row) {
            $rows[$row['company'].'-'.$row['document_type']] = $row;
        }

        return $rows;
    }

    public function test_index_returns_every_scope_of_the_running_period(): void
    {
        $this->travelTo('2026-10-16 10:00:00');
        $this->actingAsUser(['document_counter_read']);

        $response = $this->getJson('/api/document-counters');

        $response->assertOk();

        $rows = $this->rows($response);

        $this->assertSame(
            ['pas-so', 'pas-do', 'pa-so', 'pa-do'],
            array_keys($rows)
        );

        $this->assertSame('2026-10', $rows['pas-so']['period']);
        $this->assertSame('PAS/SO/10/26/01', $rows['pas-so']['next_number']);
        $this->assertSame('PAS/DO/10/26/01', $rows['pas-do']['next_number']);
        $this->assertSame('SO/PA/10/26/01', $rows['pa-so']['next_number']);
        $this->assertSame('DO/PA/10/26/01', $rows['pa-do']['next_number']);

        foreach ($rows as $row) {
            $this->assertNull($row['id']);
            $this->assertSame(0, $row['sequence']);
            $this->assertSame(1, $row['next_sequence']);
            $this->assertFalse($row['is_persisted']);
        }

        $this->assertSame(0, DocumentCounter::count());
    }

    public function test_index_exposes_the_stored_counter_of_a_scope(): void
    {
        $this->travelTo('2026-10-16 10:00:00');
        $this->actingAsUser(['document_counter_read']);

        DocumentCounter::create([
            'company' => CompanyEnum::PAS,
            'document_type' => DocumentType::SO,
            'period' => '2026-10',
            'sequence' => 9,
        ]);

        $row = $this->rows($this->getJson('/api/document-counters'))['pas-so'];

        $this->assertNotNull($row['id']);
        $this->assertTrue($row['is_persisted']);
        $this->assertSame(9, $row['sequence']);
        $this->assertSame(10, $row['next_sequence']);
        $this->assertSame('PAS/SO/10/26/10', $row['next_number']);
    }

    public function test_index_can_target_another_period(): void
    {
        $this->travelTo('2026-10-16 10:00:00');
        $this->actingAsUser(['document_counter_read']);

        $response = $this->getJson('/api/document-counters?period=2026-09');

        $response->assertOk();

        $rows = $this->rows($response);

        $this->assertSame('2026-09', $rows['pas-so']['period']);
        $this->assertSame('PAS/SO/09/26/01', $rows['pas-so']['next_number']);
        $this->assertSame(0, DocumentCounter::count());
    }

    public function test_index_filters_by_company_and_document_type(): void
    {
        $this->travelTo('2026-10-16 10:00:00');
        $this->actingAsUser(['document_counter_read']);

        $rows = $this->rows($this->getJson('/api/document-counters?company=pa&document_type=do'));

        $this->assertSame(['pa-do'], array_keys($rows));
        $this->assertSame('DO/PA/10/26/01', $rows['pa-do']['next_number']);
    }

    public function test_index_rejects_an_invalid_period(): void
    {
        $this->actingAsUser(['document_counter_read']);

        $this->getJson('/api/document-counters?period=2026-13')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);
    }

    public function test_index_rejects_an_unknown_company(): void
    {
        $this->actingAsUser(['document_counter_read']);

        $this->getJson('/api/document-counters?company=xyz')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company']);
    }

    public function test_update_creates_the_counter_of_a_period(): void
    {
        $this->travelTo('2026-10-16 10:00:00');
        $this->actingAsUser(['document_counter_edit']);

        $response = $this->putJson('/api/document-counters', [
            'company' => CompanyEnum::PAS,
            'document_type' => DocumentType::SO,
            'period' => '2026-10',
            'next_sequence' => 10,
        ]);

        $response->assertCreated();

        $this->assertSame(1, DocumentCounter::count());
        $this->assertDatabaseHas('document_counters', [
            'company' => CompanyEnum::PAS,
            'document_type' => DocumentType::SO,
            'period' => '2026-10',
            'sequence' => 9,
        ]);

        $this->assertTrue($response->json('data.is_persisted'));
        $this->assertSame(10, $response->json('data.next_sequence'));
        $this->assertSame('PAS/SO/10/26/10', $response->json('data.next_number'));
    }

    public function test_update_replaces_the_sequence_of_an_existing_counter(): void
    {
        $this->travelTo('2026-10-16 10:00:00');
        $this->actingAsUser(['document_counter_edit']);

        DocumentCounter::create([
            'company' => CompanyEnum::PAS,
            'document_type' => DocumentType::SO,
            'period' => '2026-10',
            'sequence' => 3,
        ]);

        $this->putJson('/api/document-counters', [
            'company' => CompanyEnum::PAS,
            'document_type' => DocumentType::SO,
            'period' => '2026-10',
            'next_sequence' => 1,
        ])->assertOk();

        $this->assertSame(1, DocumentCounter::count());
        $this->assertSame(0, DocumentCounter::first()->sequence);
    }

    public function test_update_rejects_an_invalid_payload(): void
    {
        $this->actingAsUser(['document_counter_edit']);

        $this->putJson('/api/document-counters', [
            'company' => 'xyz',
            'document_type' => DocumentType::SO,
            'period' => '2026-10',
            'next_sequence' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors(['company']);

        $this->putJson('/api/document-counters', [
            'company' => CompanyEnum::PAS,
            'document_type' => 'xx',
            'period' => '2026-10',
            'next_sequence' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors(['document_type']);

        $this->putJson('/api/document-counters', [
            'company' => CompanyEnum::PAS,
            'document_type' => DocumentType::SO,
            'period' => '2026-13',
            'next_sequence' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors(['period']);

        $this->putJson('/api/document-counters', [
            'company' => CompanyEnum::PAS,
            'document_type' => DocumentType::SO,
            'period' => '2026-10',
            'next_sequence' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors(['next_sequence']);

        $this->assertSame(0, DocumentCounter::count());
    }

    public function test_saved_counter_drives_the_next_invoice_number(): void
    {
        $this->travelTo('2026-10-16 10:00:00');
        $this->actingAsUser(['document_counter_edit']);

        $this->putJson('/api/document-counters', [
            'company' => CompanyEnum::PAS,
            'document_type' => DocumentType::SO,
            'period' => '2026-10',
            'next_sequence' => 7,
        ])->assertCreated();

        $salesOrder = $this->createSalesOrder(CompanyEnum::PAS());

        $this->assertSame('PAS/SO/10/26/07', $salesOrder->invoice_no);
        $this->assertSame(7, DocumentCounter::first()->sequence);
    }

    public function test_index_requires_the_read_permission(): void
    {
        $this->actingAsUser([]);

        $this->getJson('/api/document-counters')->assertForbidden();
    }

    public function test_update_requires_the_edit_permission(): void
    {
        $this->actingAsUser(['document_counter_read']);

        $this->putJson('/api/document-counters', [
            'company' => CompanyEnum::PAS,
            'document_type' => DocumentType::SO,
            'period' => '2026-10',
            'next_sequence' => 5,
        ])->assertForbidden();

        $this->assertSame(0, DocumentCounter::count());
    }

    private function createSalesOrder(CompanyEnum $company): SalesOrder
    {
        $warehouse = Warehouse::create([
            'code' => 'WH-'.uniqid(),
            'name' => 'Test Warehouse',
            'company_name' => 'Test Company',
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => Product::create([
                'product_category_id' => ProductCategory::create(['name' => 'Cat '.uniqid()])->id,
                'product_brand_id' => ProductBrand::create(['name' => 'Brand '.uniqid()])->id,
                'company' => CompanyEnum::PAS,
                'name' => 'Product '.uniqid(),
            ])->id,
            'uom_id' => Uom::create(['name' => 'pcs '.uniqid()])->id,
            'name' => 'Unit '.uniqid(),
            'price' => 10000,
            'code' => 'PU-'.uniqid(),
            'is_generate_qr' => false,
        ]);

        StockProductUnit::create([
            'product_unit_id' => $productUnit->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 1000,
        ]);

        $user = User::create([
            'name' => 'Buyer',
            'email' => 'buyer-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'type' => UserType::Customer,
        ]);

        return SalesOrder::create([
            'user_id' => $user->id,
            'reseller_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'company' => $company->value,
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'shipment_estimation_datetime' => now()->format('Y-m-d H:i:s'),
            'shipment_fee' => 0,
            'additional_discount' => 0,
            'price' => 10000,
            'is_invoice' => false,
            'type' => 'default',
            'details' => [
                [
                    'product_unit_id' => $productUnit->id,
                    'warehouse_id' => $warehouse->id,
                    'qty' => 1,
                    'unit_price' => 10000,
                    'discount' => 0,
                    'tax' => 0,
                    'total_price' => 10000,
                ],
            ],
        ]);
    }
}
