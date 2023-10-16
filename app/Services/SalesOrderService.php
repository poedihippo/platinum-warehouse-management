<?php

namespace App\Services;

use App\Models\SalesOrderDetail;

class SalesOrderService
{
    /**
     * validation total price between BE calculation with FE calculation
     *
     * @param int|float $totalPrice total price from FE calculation
     * @param array $items SO items data
     *
     * @return bool
     */
    public static function validateTotalPrice(int $totalPrice, int $shipmentFee = 0, array $items): bool
    {
        $cekTotalPrice = 0;
        $pricePerItem = 0;
        foreach ($items as $item) {
            $pricePerItem = $item['unit_price'] * $item['qty'];
            $discount = $pricePerItem * ($item['discount'] / 100);
            $pricePerItem = $pricePerItem - $discount;
            if ($item['tax'] == 1) {
                $tax = $pricePerItem * 0.11;
                $pricePerItem = $pricePerItem + $tax;
            }
            $cekTotalPrice += $pricePerItem;
        }

        $cekTotalPrice += $shipmentFee;

        if ($cekTotalPrice != $totalPrice) return false;
        return true;
    }

    /**
     * count fulfilled_qty in sales_order_details
     *
     * @param SalesOrderDetail $salesOrderDetail
     *
     * @return void
     */
    public static function countFulfilledQty(SalesOrderDetail $salesOrderDetail): void
    {
        $salesOrderDetail->refresh();

        $salesOrderDetail->update([
            'fulfilled_qty' => $salesOrderDetail->salesOrderItems->count()
        ]);
    }
}
