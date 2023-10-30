<?php
namespace App\Pipes\Order;

use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;

class MakeOrderDetails
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $rawSoruce = $salesOrder->raw_source;
        $items = collect($rawSoruce['items']);
        $productUnits = ProductUnit::withTrashed()->whereIn('id', $items->pluck('product_unit_id'))->with('product', fn($q) => $q->with('productBrand', 'productCategory'))->get()->keyBy('id');

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
            $orderDetail->warehouse_id = $item['warehouse_id'];

            return $orderDetail;
        });

        $salesOrder->sales_order_details = $salesOrderDetails;
        $salesOrder->price = $salesOrder->sales_order_details->sum('total_price') ?? 0;

        return $next($salesOrder);
    }
}
