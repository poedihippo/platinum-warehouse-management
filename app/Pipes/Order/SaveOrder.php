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

                if ($reseller) {
                    $records['reseller'] = $reseller->setHidden(['email_verified_at', 'remember_token', 'created_at', 'updated_at', 'deleted_at'])?->toArray() ?? [];
                }

                $salesOrder->records = $records;
            }

            $salesOrderDetails = $salesOrder->details;
            unset($salesOrder->details);

            if (request()->segment(2) == 'invoices' && request()->method() === 'POST' && $salesOrder->warehouse) {
                $salesOrder->invoice_no = SalesOrderService::getSoNumber($salesOrder->warehouse);
            }

            $salesOrder->save();
            $salesOrder->details()->saveMany($salesOrderDetails);

            if ($salesOrder->is_invoice) {
                $this->createSalesOrderItems($salesOrder, $salesOrderDetails);
            }

            return $salesOrder;
        });

        return $next($salesOrder);
    }

    private function createReseller(SalesOrder $salesOrder): ?User
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

    private function createSalesOrderItems(SalesOrder $salesOrder, \Illuminate\Support\Collection $salesOrderDetails): void
    {
        $stockIdsByProductUnit = collect($salesOrder->raw_source['items'] ?? [])
            ->map(fn ($item) => $item['stock_ids'] ?? [])
            ->filter(fn ($stockIds) => ! empty($stockIds))
            ->all();

        $salesOrderDetails->each(function (SalesOrderDetail $salesOrderDetail) use ($stockIdsByProductUnit) {
            $stockIds = $stockIdsByProductUnit[$salesOrderDetail->product_unit_id] ?? [];

            if (! empty($stockIds)) {
                $this->createScannedSalesOrderItems($salesOrderDetail, $stockIds);
            } else {
                $this->autoReserveSalesOrderItems($salesOrderDetail);
            }

            SalesOrderService::countFulfilledQty($salesOrderDetail);
        });
    }

    private function autoReserveSalesOrderItems(SalesOrderDetail $salesOrderDetail): void
    {
        $stocks = \App\Models\Stock::whereAvailableStock()
            ->whereHas('stockProductUnit', fn ($q) => $q->where('product_unit_id', $salesOrderDetail->product_unit_id)->where('warehouse_id', $salesOrderDetail->warehouse_id))
            ->limit($salesOrderDetail->qty)
            ->get(['id'])->map(fn ($stock) => ['stock_id' => $stock->id]);

        if ($stocks->count() < $salesOrderDetail->qty) {
            throw new \Exception(sprintf('Stok %s tidak tersedia', $salesOrderDetail->productUnit->name), \Illuminate\Http\Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $salesOrderDetail->salesOrderItems()->createMany($stocks);
    }

    private function createScannedSalesOrderItems(SalesOrderDetail $salesOrderDetail, array $stockIds): void
    {
        \App\Models\Stock::whereIn('id', $stockIds)->get(['id', 'parent_id'])->each(function ($stock) use ($salesOrderDetail) {
            $this->createSalesOrderItemWithChildren($salesOrderDetail, $stock);
        });
    }

    private function createSalesOrderItemWithChildren(SalesOrderDetail $salesOrderDetail, \App\Models\Stock $stock): void
    {
        $childIds = \App\Models\Stock::where('parent_id', $stock->id)->pluck('id');

        if ($childIds->isNotEmpty()) {
            $parentItem = $salesOrderDetail->salesOrderItems()->create([
                'stock_id' => $stock->id,
                'is_parent' => true,
            ]);

            $childRows = $childIds->map(fn ($childId) => [
                'stock_id' => $childId,
                'parent_id' => $parentItem->id,
            ])->all();

            $salesOrderDetail->salesOrderItems()->createMany($childRows);

            return;
        }

        $salesOrderDetail->salesOrderItems()->create([
            'stock_id' => $stock->id,
            'is_parent' => false,
        ]);
    }
}
