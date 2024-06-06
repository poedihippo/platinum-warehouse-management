<?php

namespace App\Pipes\Order;

use App\Enums\UserType;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\User;
use App\Services\SalesOrderService;
use Illuminate\Support\Facades\DB;

class SaveOrder
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $salesOrder = DB::transaction(function () use ($salesOrder) {
            if (
                isset($salesOrder->raw_source['customer_name']) &&
                $salesOrder->raw_source['customer_name'] != '' &&
                $reseller = $this->createReseller($salesOrder)
            ) {
                $salesOrder->reseller_id = $reseller->id;

                $records = $salesOrder->records ?? [];

                if ($reseller) $records['reseller'] = $reseller->setHidden(['email_verified_at', 'remember_token', 'created_at', 'updated_at', 'deleted_at'])?->toArray() ?? [];

                $salesOrder->records = $records;
            }

            $salesOrderDetails = $salesOrder->details;
            unset($salesOrder->details);

            if (request()->segment(2) == 'invoices' && request()->method() === 'POST' && $salesOrder->warehouse) {
                $salesOrder->invoice_no = SalesOrderService::getSoNumber($salesOrder->warehouse);
            }

            $salesOrder->save();
            $salesOrder->details()->saveMany($salesOrderDetails);

            if ($salesOrder->is_invoice) $this->createSalesOrderItems($salesOrderDetails, $salesOrder->warehouse_id);

            return $salesOrder;
        });

        return $next($salesOrder);
    }

    private function createReseller(SalesOrder $salesOrder): User|null
    {
        $rawSoruce = $salesOrder->raw_source;
        try {
            return User::create([
                'name' => $rawSoruce['customer_name'],
                'phone' => $rawSoruce['customer_phone'],
                'address' => $rawSoruce['customer_address'] ?? null,
                'type' => UserType::CustomerEvent,
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }

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
