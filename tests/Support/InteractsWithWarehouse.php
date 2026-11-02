<?php

namespace Tests\Support;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\StockProductUnit;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;

trait InteractsWithWarehouse
{
    protected function makeUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'User '.uniqid(),
            'email' => 'user-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'type' => 'admin',
        ], $attributes));
    }

    protected function actingAsAdmin(array $attributes = []): User
    {
        $user = $this->makeUser($attributes);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');

        Sanctum::actingAs($user, ['warehouse']);

        return $user;
    }

    protected function actingAsStaff(array $permissions = [], array $warehouses = [], array $attributes = []): User
    {
        $user = $this->makeUser($attributes);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        if ($permissions) {
            $user->givePermissionTo($permissions);
        }

        if ($warehouses) {
            $user->warehouses()->sync(collect($warehouses)->map(fn ($w) => $w instanceof Warehouse ? $w->id : $w)->all());
        }

        Sanctum::actingAs($user, ['warehouse']);

        return $user;
    }

    protected function makeWarehouse(array $attributes = []): Warehouse
    {
        return Warehouse::create(array_merge([
            'code' => 'WH'.substr(uniqid(), -6),
            'name' => 'Warehouse '.uniqid(),
            'company_name' => 'Test Company',
        ], $attributes));
    }

    protected function makeProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'product_category_id' => ProductCategory::create(['name' => 'Category '.uniqid()])->id,
            'product_brand_id' => ProductBrand::create(['name' => 'Brand '.uniqid()])->id,
            'company' => 'pas',
            'name' => 'Product '.uniqid(),
        ], $attributes));
    }

    protected function makeProductUnit(bool $qr = true, array $attributes = [], ?Product $product = null): ProductUnit
    {
        $product ??= $this->makeProduct();

        return ProductUnit::create(array_merge([
            'product_id' => $product->id,
            'uom_id' => Uom::create(['name' => 'uom-'.uniqid()])->id,
            'name' => 'Unit '.uniqid(),
            'price' => 100000,
            'code' => 'PU-'.uniqid(),
            'is_generate_qr' => $qr,
            'is_ppn' => false,
        ], $attributes));
    }

    protected function spu(ProductUnit $productUnit, Warehouse $warehouse): StockProductUnit
    {
        return StockProductUnit::where('product_unit_id', $productUnit->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();
    }

    protected function makeStocks(StockProductUnit $stockProductUnit, int $count = 1, array $attributes = []): Collection
    {
        return collect(range(1, $count))->map(fn () => Stock::create(array_merge([
            'stock_product_unit_id' => $stockProductUnit->id,
        ], $attributes)));
    }

    protected function makeGroupedStock(StockProductUnit $stockProductUnit, int $children = 2, array $attributes = []): Stock
    {
        $parent = Stock::create(array_merge([
            'stock_product_unit_id' => $stockProductUnit->id,
            'description' => 'GROUP '.uniqid(),
        ], $attributes));

        $this->makeStocks($stockProductUnit, $children, ['parent_id' => $parent->id]);

        return $parent;
    }

    protected function makeSalesOrder(Warehouse $warehouse, array $lines, array $attributes = []): SalesOrder
    {
        $reseller = $this->makeUser(['type' => 'reseller', 'company' => 'pas']);
        $salesOrder = SalesOrder::create(array_merge([
            'is_invoice' => false,
            'invoice_no' => 'SO/'.uniqid(),
            'company' => 'pas',
            'type' => 'default',
            'user_id' => $reseller->id,
            'reseller_id' => $reseller->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => now(),
            'shipment_estimation_datetime' => now(),
            'price' => 0,
            'additional_discount' => 0,
            'auto_discount' => 0,
            'shipment_fee' => 0,
        ], $attributes));

        foreach ($lines as [$productUnit, $qty]) {
            SalesOrderDetail::create([
                'sales_order_id' => $salesOrder->id,
                'product_unit_id' => $productUnit->id,
                'warehouse_id' => $warehouse->id,
                'qty' => $qty,
                'unit_price' => 1000,
                'total_price' => 1000 * $qty,
            ]);
        }

        return $salesOrder->load('details');
    }

    protected function makeDeliveryOrder(SalesOrder $salesOrder, array $attributes = []): DeliveryOrder
    {
        $deliveryOrder = DeliveryOrder::create(array_merge([
            'invoice_no' => 'DO/'.uniqid(),
            'company' => 'pas',
            'warehouse_id' => $salesOrder->warehouse_id,
            'reseller_id' => $salesOrder->reseller_id,
            'sales_order_id' => $salesOrder->id,
            'transaction_date' => now(),
            'shipment_estimation_datetime' => now(),
        ], $attributes));

        foreach ($salesOrder->details as $detail) {
            DeliveryOrderDetail::create([
                'delivery_order_id' => $deliveryOrder->id,
                'sales_order_detail_id' => $detail->id,
                'qty' => $detail->qty,
            ]);
        }

        return $deliveryOrder->load('details');
    }

    protected function histories(Model $subject): Collection
    {
        return StockHistory::where('model_type', $subject->getMorphClass())
            ->where('model_id', $subject->getKey())
            ->orderBy('id')
            ->get();
    }

    protected function assertHistory(Model $subject, array $expected): StockHistory
    {
        $history = $this->histories($subject)->first(function (StockHistory $history) use ($expected) {
            foreach ($expected as $key => $value) {
                if ($history->{$key} != $value) {
                    return false;
                }
            }

            return true;
        });

        $this->assertNotNull($history, 'No stock history for '.$subject::class.'#'.$subject->getKey().' matching '.json_encode($expected).'; found: '.json_encode($this->histories($subject)->toArray()));

        return $history;
    }
}
