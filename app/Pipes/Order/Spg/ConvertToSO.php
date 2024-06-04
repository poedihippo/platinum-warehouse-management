<?php

namespace App\Pipes\Order\Spg;

use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Services\SalesOrderService;
use Illuminate\Support\Facades\DB;

class ConvertToSO
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $salesOrder = DB::transaction(function () use ($salesOrder) {
            $oldDetails = $salesOrder->details;

            $salesOrderDetails = $salesOrder->sales_order_details;
            unset($salesOrder->sales_order_details);

            $salesOrder->save();
            $salesOrder->details()->saveMany($salesOrderDetails);

            $oldDetails->each->delete();

            // if ($salesOrder->is_invoice) $this->createSalesOrderItems($salesOrderDetails, $salesOrder->warehouse_id);
            $this->createSalesOrderItems($salesOrderDetails, $salesOrder->warehouse_id);

            return $salesOrder;
        });

        return $next($salesOrder);
    }

    // private function createReseller(SalesOrder $salesOrder): User|null
    // {
    //     $rawSoruce = $salesOrder->raw_source;
    //     try {
    //         return User::create([
    //             'name' => $rawSoruce['customer_name'],
    //             'phone' => $rawSoruce['customer_phone'],
    //             'address' => $rawSoruce['customer_address'] ?? null,
    //             'type' => UserType::CustomerEvent,
    //         ]);
    //     } catch (\Exception $e) {
    //         return null;
    //     }
    // }

    private function createSalesOrderItems(\Illuminate\Support\Collection $salesOrderDetails, int $warehouseId): void
    {
        $salesOrderDetails->each(function (SalesOrderDetail $salesOrderDetail) use ($warehouseId) {
            $stocks = \App\Models\Stock::whereAvailableStock()
                ->whereHas('stockProductUnit', fn ($q) => $q->where('product_unit_id', $salesOrderDetail->product_unit_id)->where('warehouse_id', $warehouseId))
                ->limit($salesOrderDetail->qty)
                ->get(['id'])->map(fn ($stock) => ['stock_id' => $stock->id]);

            if ($stocks->count() < $salesOrderDetail->qty) throw new \Exception(sprintf('Stok %s tidak tersedia', $salesOrderDetail->productUnit->name), \Illuminate\Http\Response::HTTP_UNPROCESSABLE_ENTITY);

            $salesOrderDetail->salesOrderItems()->createMany($stocks);

            SalesOrderService::countFulfilledQty($salesOrderDetail);
        });
    }
}
