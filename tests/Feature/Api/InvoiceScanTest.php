<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\Stock;
use App\Models\StockProductUnit;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Pipes\Order\SaveOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\TestCase;

class InvoiceScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'invoice_create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'invoice_delete', 'guard_name' => 'web']);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'type' => 'admin',
        ]);
        $admin->givePermissionTo('invoice_create', 'invoice_delete');

        Sanctum::actingAs($admin, ['warehouse']);

        return $admin;
    }

    private function createScannedContext(): array
    {
        $warehouse = Warehouse::create([
            'code' => 'WH-'.uniqid(),
            'name' => 'Test Warehouse',
            'company_name' => 'Test Company',
        ]);

        $uom = Uom::create(['name' => 'pcs']);

        $brand = ProductBrand::create(['name' => 'Test Brand '.uniqid()]);
        $category = ProductCategory::create(['name' => 'Test Category '.uniqid()]);

        $product = Product::create([
            'product_category_id' => $category->id,
            'product_brand_id' => $brand->id,
            'company' => 'pas',
            'name' => 'Test Product',
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'name' => 'Test Unit',
            'price' => 100000,
            'refer_qty' => null,
            'code' => 'PU-'.uniqid(),
            'is_generate_qr' => true,
            'is_ppn' => false,
        ]);

        $stockProductUnit = StockProductUnit::where('product_unit_id', $productUnit->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();

        $parent = Stock::create(['stock_product_unit_id' => $stockProductUnit->id]);
        $childA = Stock::create(['stock_product_unit_id' => $stockProductUnit->id, 'parent_id' => $parent->id]);
        $childB = Stock::create(['stock_product_unit_id' => $stockProductUnit->id, 'parent_id' => $parent->id]);

        return compact('warehouse', 'productUnit', 'stockProductUnit', 'parent', 'childA', 'childB');
    }

    private function sampleSalesOrder(Warehouse $warehouse, User $user): SalesOrder
    {
        $reseller = User::create([
            'name' => 'Reseller '.uniqid(),
            'email' => 'reseller-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'type' => 'customer_event',
        ]);

        return SalesOrder::create([
            'is_invoice' => true,
            'invoice_no' => 'PAS/SO/TEST/'.uniqid(),
            'type' => 'default',
            'user_id' => $user->id,
            'reseller_id' => $reseller->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => now(),
            'shipment_estimation_datetime' => now(),
            'price' => 100000,
            'additional_discount' => 0,
            'auto_discount' => 0,
            'shipment_fee' => 0,
        ]);
    }

    private function invokePrivate(SaveOrder $saveOrder, string $method, ...$args)
    {
        $reflection = new ReflectionMethod($saveOrder, $method);

        return $reflection->invoke($saveOrder, ...$args);
    }

    public function test_scanned_parent_stock_is_grouped_with_children_sales_order_items(): void
    {
        $this->actingAsAdmin();
        $context = $this->createScannedContext();

        $salesOrder = $this->sampleSalesOrder($context['warehouse'], User::where('type', 'admin')->first());
        $detail = SalesOrderDetail::create([
            'sales_order_id' => $salesOrder->id,
            'product_unit_id' => $context['productUnit']->id,
            'warehouse_id' => $context['warehouse']->id,
            'qty' => 1,
            'unit_price' => 100000,
            'total_price' => 100000,
        ]);

        $this->invokePrivate(app(SaveOrder::class), 'createScannedSalesOrderItems', $detail, [$context['parent']->id]);

        $items = $detail->salesOrderItems()->get();

        $parentItem = $items->firstWhere('stock_id', $context['parent']->id);
        $this->assertNotNull($parentItem);
        $this->assertTrue((bool) $parentItem->is_parent);
        $this->assertNull($parentItem->parent_id);

        $childIds = $items->whereNotNull('parent_id')->pluck('stock_id');
        $this->assertCount(2, $childIds);
        $this->assertEqualsCanonicalizing([$context['childA']->id, $context['childB']->id], $childIds->all());
        $this->assertTrue($items->whereNotNull('parent_id')->every(fn ($item) => $item->parent_id === $parentItem->id));
    }

    public function test_scanned_leaf_stock_creates_single_non_parent_sales_order_item(): void
    {
        $this->actingAsAdmin();
        $context = $this->createScannedContext();

        $salesOrder = $this->sampleSalesOrder($context['warehouse'], User::where('type', 'admin')->first());
        $detail = SalesOrderDetail::create([
            'sales_order_id' => $salesOrder->id,
            'product_unit_id' => $context['productUnit']->id,
            'warehouse_id' => $context['warehouse']->id,
            'qty' => 1,
            'unit_price' => 100000,
            'total_price' => 100000,
        ]);

        $this->invokePrivate(app(SaveOrder::class), 'createScannedSalesOrderItems', $detail, [$context['childA']->id]);

        $item = $detail->salesOrderItems()->firstOrFail();
        $this->assertSame($context['childA']->id, $item->stock_id);
        $this->assertFalse((bool) $item->is_parent);
        $this->assertNull($item->parent_id);
    }

    public function test_destroy_deletes_scanned_sales_order_items_and_returns_stock_available(): void
    {
        $admin = $this->actingAsAdmin();
        $context = $this->createScannedContext();

        $salesOrder = $this->sampleSalesOrder($context['warehouse'], $admin);
        $detail = SalesOrderDetail::create([
            'sales_order_id' => $salesOrder->id,
            'product_unit_id' => $context['productUnit']->id,
            'warehouse_id' => $context['warehouse']->id,
            'qty' => 1,
            'unit_price' => 100000,
            'total_price' => 100000,
        ]);
        $detail->salesOrderItems()->create(['stock_id' => $context['parent']->id]);

        $this->assertTrue($context['parent']->fresh()->salesOrderItems()->whereNotReturned()->exists());

        $this->deleteJson("/api/invoices/{$salesOrder->id}")->assertOk();

        $this->assertDatabaseMissing('sales_orders', ['id' => $salesOrder->id]);
        $this->assertTrue($context['parent']->fresh()->salesOrderItem()->doesntExist());
    }
}
