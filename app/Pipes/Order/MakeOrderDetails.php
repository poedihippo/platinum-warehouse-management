<?php

namespace App\Pipes\Order;

use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\StockProductUnit;

class MakeOrderDetails
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $rawSoruce = $salesOrder->raw_source;
        $items = collect($rawSoruce['items']);
        $productUnits = ProductUnit::withTrashed()->whereIn('id', $items->pluck('product_unit_id'))
            ->with('product', fn ($q) => $q->select('id', 'name', 'product_brand_id', 'product_category_id')
                ->with([
                    'productBrand' => fn ($q) => $q->select('id', 'name'),
                    'productCategory' => fn ($q) => $q->select('id', 'name'),
                ]))->get()->keyBy('id');

        $salesOrderDetails = $items->map(function ($item) use ($productUnits) {
            $productUnit = $productUnits[$item['product_unit_id']];

            $orderDetail = new SalesOrderDetail;
            $orderDetail->product_unit_id = $productUnit->id;
            $orderDetail->packaging_id = empty($item['packaging_id']) ? null : $item['packaging_id'];
            $orderDetail->qty = empty($item['qty']) ? 1 : (int) $item['qty'];
            $orderDetail->unit_price = empty($item['unit_price']) ? ($productUnit->price ?? 0) : (int) $item['unit_price'];
            $orderDetail->discount = empty($item['discount']) ? 0 : (int) $item['discount'];
            $orderDetail->tax = isset($item['tax']) && $item['tax'] == 1 ? 11 : 0;
            $orderDetail->total_price = empty($item['total_price']) ? 0 : (int) $item['total_price'];
            $orderDetail->warehouse_id = empty($item['warehouse_id']) ? null : $item['warehouse_id'];

            if (!empty($orderDetail->warehouse_id)) {
                $stock = 0;
                if ($productUnit->is_generate_qr) {
                    $stock = StockProductUnit::where('product_unit_id', $productUnit->id)
                        ->where('warehouse_id', $orderDetail->warehouse_id)
                        ->first(['qty'])?->qty ?? 0;
                } else {
                    $stock = StockProductUnit::where('product_unit_id', $productUnit->id)
                        ->where('warehouse_id', $orderDetail->warehouse_id)
                        ->withCount('stocks', fn ($q) => $q->whereAvailableStock()->whereNull('description'))
                        ->first(['id'])?->stocks_count ?? 0;
                }

                $productUnit->stock = $stock;
            }
            $orderDetail->product_unit = $productUnit;

            return $orderDetail;
        });

        $salesOrder->details = $salesOrderDetails;
        $salesOrder->price = $salesOrder->details->sum('total_price') ?? 0;

        return $next($salesOrder);
    }
}
