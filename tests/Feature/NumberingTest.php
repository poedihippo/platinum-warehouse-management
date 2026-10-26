<?php

namespace Tests\Feature;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use App\Models\DeliveryOrder;
use App\Models\DocumentCounter;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\StockProductUnit;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NumberingTest extends TestCase
{
    use RefreshDatabase;

    private function createBasicContext(): array
    {
        $warehouse = Warehouse::create([
            'code' => 'WH-'.uniqid(),
            'name' => 'Test Warehouse',
            'company_name' => 'Test Company',
        ]);

        $uom = Uom::create(['name' => 'pcs']);

        $category = ProductCategory::create(['name' => 'Cat '.uniqid()]);
        $brand = ProductBrand::create(['name' => 'Brand '.uniqid()]);

        $product = Product::create([
            'product_category_id' => $category->id,
            'product_brand_id' => $brand->id,
            'company' => 'pas',
            'name' => 'Product '.uniqid(),
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'name' => 'Unit '.uniqid(),
            'price' => 10000,
            'code' => 'PU-'.uniqid(),
            'is_generate_qr' => false,
        ]);

        $stockProductUnit = StockProductUnit::create([
            'product_unit_id' => $productUnit->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 1000,
        ]);

        $user = User::create([
            'name' => 'Test',
            'email' => 'test-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'type' => 'admin',
        ]);

        return compact('warehouse', 'uom', 'category', 'brand', 'product', 'productUnit', 'stockProductUnit', 'user');
    }

    private function createNonInvoiceSo(CompanyEnum $company): SalesOrder
    {
        $ctx = $this->createBasicContext();

        return SalesOrder::create([
            'user_id' => $ctx['user']->id,
            'reseller_id' => $ctx['user']->id,
            'warehouse_id' => $ctx['warehouse']->id,
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
                    'product_unit_id' => $ctx['productUnit']->id,
                    'warehouse_id' => $ctx['warehouse']->id,
                    'qty' => 1,
                    'unit_price' => 10000,
                    'discount' => 0,
                    'tax' => 0,
                    'total_price' => 10000,
                ],
            ],
        ]);
    }

    public function test_so_number_is_scoped_per_company(): void
    {
        Carbon::setTestNow('2026-10-16 00:00:00');

        $soPas1 = $this->createNonInvoiceSo(CompanyEnum::PAS());
        $soPas2 = $this->createNonInvoiceSo(CompanyEnum::PAS());
        $soPa1 = $this->createNonInvoiceSo(CompanyEnum::PA());
        $soPa2 = $this->createNonInvoiceSo(CompanyEnum::PA());

        $this->assertSame('PAS/SO/10/26/01', $soPas1->invoice_no);
        $this->assertSame('PAS/SO/10/26/02', $soPas2->invoice_no);
        $this->assertSame('SO/PA/10/26/01', $soPa1->invoice_no);
        $this->assertSame('SO/PA/10/26/02', $soPa2->invoice_no);

        Carbon::setTestNow();
    }

    public function test_do_number_uses_company_format(): void
    {
        Carbon::setTestNow('2026-10-16 00:00:00');

        $ctx = $this->createBasicContext();

        $doPas = DeliveryOrder::create([
            'user_id' => $ctx['user']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'reseller_id' => $ctx['user']->id,
            'company' => CompanyEnum::PAS()->value,
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'shipment_estimation_datetime' => now()->format('Y-m-d H:i:s'),
        ]);

        $doPa = DeliveryOrder::create([
            'user_id' => $ctx['user']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'reseller_id' => $ctx['user']->id,
            'company' => CompanyEnum::PA()->value,
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'shipment_estimation_datetime' => now()->format('Y-m-d H:i:s'),
        ]);

        $this->assertSame('PAS/DO/10/26/01', $doPas->invoice_no);
        $this->assertSame('DO/PA/10/26/01', $doPa->invoice_no);

        Carbon::setTestNow();
    }

    public function test_so_and_do_counters_are_isolated(): void
    {
        Carbon::setTestNow('2026-10-16 00:00:00');

        $ctx = $this->createBasicContext();

        $so = SalesOrder::create([
            'user_id' => $ctx['user']->id,
            'reseller_id' => $ctx['user']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'company' => CompanyEnum::PAS()->value,
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'shipment_estimation_datetime' => now()->format('Y-m-d H:i:s'),
            'shipment_fee' => 0,
            'additional_discount' => 0,
            'price' => 10000,
            'is_invoice' => false,
            'type' => 'default',
            'details' => [
                [
                    'product_unit_id' => $ctx['productUnit']->id,
                    'warehouse_id' => $ctx['warehouse']->id,
                    'qty' => 1,
                    'unit_price' => 10000,
                    'discount' => 0,
                    'tax' => 0,
                    'total_price' => 10000,
                ],
            ],
        ]);

        $do = DeliveryOrder::create([
            'user_id' => $ctx['user']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'reseller_id' => $ctx['user']->id,
            'company' => CompanyEnum::PAS()->value,
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'shipment_estimation_datetime' => now()->format('Y-m-d H:i:s'),
        ]);

        $this->assertSame('PAS/SO/10/26/01', $so->invoice_no);
        $this->assertSame('PAS/DO/10/26/01', $do->invoice_no);

        Carbon::setTestNow();
    }

    public function test_sequence_resets_on_new_period(): void
    {
        Carbon::setTestNow('2026-10-16 00:00:00');

        $so1 = $this->createNonInvoiceSo(CompanyEnum::PAS());
        $this->assertSame('PAS/SO/10/26/01', $so1->invoice_no);

        Carbon::setTestNow('2026-11-01 00:00:00');

        $so2 = $this->createNonInvoiceSo(CompanyEnum::PAS());
        $this->assertSame('PAS/SO/11/26/01', $so2->invoice_no);

        Carbon::setTestNow();
    }

    public function test_sequence_grows_beyond_two_digits(): void
    {
        Carbon::setTestNow('2026-10-16 00:00:00');

        DocumentCounter::create([
            'company' => CompanyEnum::PAS()->value,
            'document_type' => DocumentType::SO()->value,
            'period' => '2026-10',
            'sequence' => 99,
        ]);

        $so = $this->createNonInvoiceSo(CompanyEnum::PAS());
        $this->assertSame('PAS/SO/10/26/100', $so->invoice_no);

        Carbon::setTestNow();
    }

    public function test_manual_invoice_no_is_respected(): void
    {
        Carbon::setTestNow('2026-10-16 00:00:00');

        $ctx = $this->createBasicContext();

        $so = SalesOrder::create([
            'user_id' => $ctx['user']->id,
            'reseller_id' => $ctx['user']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'company' => CompanyEnum::PAS()->value,
            'invoice_no' => 'MANUAL/001',
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'shipment_estimation_datetime' => now()->format('Y-m-d H:i:s'),
            'shipment_fee' => 0,
            'additional_discount' => 0,
            'price' => 10000,
            'is_invoice' => false,
            'type' => 'default',
            'details' => [
                [
                    'product_unit_id' => $ctx['productUnit']->id,
                    'warehouse_id' => $ctx['warehouse']->id,
                    'qty' => 1,
                    'unit_price' => 10000,
                    'discount' => 0,
                    'tax' => 0,
                    'total_price' => 10000,
                ],
            ],
        ]);

        $this->assertSame('MANUAL/001', $so->invoice_no);
        $this->assertSame(0, DocumentCounter::count());

        Carbon::setTestNow();
    }

    public function test_blank_invoice_no_is_generated(): void
    {
        Carbon::setTestNow('2026-10-16 00:00:00');

        $ctx = $this->createBasicContext();

        $so = SalesOrder::create([
            'user_id' => $ctx['user']->id,
            'reseller_id' => $ctx['user']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'company' => CompanyEnum::PAS()->value,
            'invoice_no' => '',
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'shipment_estimation_datetime' => now()->format('Y-m-d H:i:s'),
            'shipment_fee' => 0,
            'additional_discount' => 0,
            'price' => 10000,
            'is_invoice' => false,
            'type' => 'default',
            'details' => [
                [
                    'product_unit_id' => $ctx['productUnit']->id,
                    'warehouse_id' => $ctx['warehouse']->id,
                    'qty' => 1,
                    'unit_price' => 10000,
                    'discount' => 0,
                    'tax' => 0,
                    'total_price' => 10000,
                ],
            ],
        ]);

        $this->assertSame('PAS/SO/10/26/01', $so->invoice_no);
        $this->assertSame(1, DocumentCounter::count());

        Carbon::setTestNow();
    }

    public function test_invoice_order_does_not_use_the_counter(): void
    {
        Carbon::setTestNow('2026-10-16 00:00:00');

        $ctx = $this->createBasicContext();

        $so = SalesOrder::create([
            'user_id' => $ctx['user']->id,
            'reseller_id' => $ctx['user']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'company' => CompanyEnum::PAS()->value,
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'shipment_estimation_datetime' => now()->format('Y-m-d H:i:s'),
            'shipment_fee' => 0,
            'additional_discount' => 0,
            'price' => 10000,
            'is_invoice' => true,
            'type' => 'default',
            'details' => [
                [
                    'product_unit_id' => $ctx['productUnit']->id,
                    'warehouse_id' => $ctx['warehouse']->id,
                    'qty' => 1,
                    'unit_price' => 10000,
                    'discount' => 0,
                    'tax' => 0,
                    'total_price' => 10000,
                ],
            ],
        ]);

        $this->assertNull($so->invoice_no);
        $this->assertSame(0, DocumentCounter::count());

        Carbon::setTestNow();
    }

    public function test_format_matches_company_segment_order(): void
    {
        Carbon::setTestNow('2026-10-05 00:00:00');

        $pas = NumberingService::format(CompanyEnum::PAS(), DocumentType::SO(), now(), 1);
        $pa = NumberingService::format(CompanyEnum::PA(), DocumentType::DO(), now(), 5);

        $this->assertSame('PAS/SO/10/26/01', $pas);
        $this->assertSame('DO/PA/10/26/05', $pa);

        Carbon::setTestNow();
    }

    public function test_counter_row_is_created_when_missing(): void
    {
        Carbon::setTestNow('2026-10-16 00:00:00');

        $this->assertSame(0, DocumentCounter::count());

        $number = NumberingService::next(CompanyEnum::PAS(), DocumentType::SO());

        $this->assertSame('PAS/SO/10/26/01', $number);
        $this->assertSame(1, DocumentCounter::count());

        $counter = DocumentCounter::first();
        $this->assertSame(CompanyEnum::PAS()->value, $counter->company->value);
        $this->assertSame(DocumentType::SO()->value, $counter->document_type->value);
        $this->assertSame('2026-10', $counter->period);
        $this->assertSame(1, $counter->sequence);

        Carbon::setTestNow();
    }

    public function test_existing_counter_row_is_reused(): void
    {
        Carbon::setTestNow('2026-10-16 00:00:00');

        DocumentCounter::create([
            'company' => CompanyEnum::PAS()->value,
            'document_type' => DocumentType::SO()->value,
            'period' => '2026-10',
            'sequence' => 5,
        ]);

        $number = NumberingService::next(CompanyEnum::PAS(), DocumentType::SO());

        $this->assertSame('PAS/SO/10/26/06', $number);
        $this->assertSame(1, DocumentCounter::count());
        $this->assertSame(6, DocumentCounter::first()->sequence);

        Carbon::setTestNow();
    }

    public function test_rolled_back_document_leaves_no_gap(): void
    {
        Carbon::setTestNow('2026-10-16 00:00:00');

        try {
            DB::transaction(function () {
                NumberingService::next(CompanyEnum::PAS(), DocumentType::SO());

                throw new \RuntimeException('order failed');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame('PAS/SO/10/26/01', NumberingService::next(CompanyEnum::PAS(), DocumentType::SO()));
        $this->assertSame(1, DocumentCounter::first()->sequence);

        Carbon::setTestNow();
    }

    public function test_delivery_order_rollback_releases_the_number(): void
    {
        Carbon::setTestNow('2026-10-16 00:00:00');

        $ctx = $this->createBasicContext();

        try {
            DB::transaction(function () use ($ctx) {
                DeliveryOrder::create([
                    'user_id' => $ctx['user']->id,
                    'warehouse_id' => $ctx['warehouse']->id,
                    'reseller_id' => $ctx['user']->id,
                    'company' => CompanyEnum::PAS()->value,
                    'transaction_date' => now()->format('Y-m-d H:i:s'),
                    'shipment_estimation_datetime' => now()->format('Y-m-d H:i:s'),
                ]);

                throw new \RuntimeException('delivery order failed');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, DeliveryOrder::count());

        $deliveryOrder = DeliveryOrder::create([
            'user_id' => $ctx['user']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'reseller_id' => $ctx['user']->id,
            'company' => CompanyEnum::PAS()->value,
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'shipment_estimation_datetime' => now()->format('Y-m-d H:i:s'),
        ]);

        $this->assertSame('PAS/DO/10/26/01', $deliveryOrder->invoice_no);

        Carbon::setTestNow();
    }
}
