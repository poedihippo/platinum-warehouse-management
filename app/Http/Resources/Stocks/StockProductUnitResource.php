<?php

namespace App\Http\Resources\Stocks;

use App\Http\Resources\ReceiveOrderDetailResource;
use App\Http\Resources\StockProductUnitResource as ResourcesStockProductUnitResource;
use Illuminate\Http\Resources\Json\JsonResource;

class StockProductUnitResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'childs_count' => $this->whenCounted('childs'),
            'receive_order_detail' => new ReceiveOrderDetailResource($this->whenLoaded('receiveOrderDetail')),
            'stock_product_unit' => new ResourcesStockProductUnitResource(
                $this->whenLoaded('stockProductUnit')->load(['productUnit', 'warehouse'])
            ),
        ];

        // ==============================================
        // Cek salesOrderItem → salesOrderDetail → salesOrder
        // ==============================================

        if ($this->relationLoaded('salesOrderItem')) {

            $salesOrderItem = $this->salesOrderItem;

            // salesOrderDetail harus ada & harus loaded
            if ($salesOrderItem?->relationLoaded('salesOrderDetail')) {

                $salesOrderDetail = $salesOrderItem->salesOrderDetail;

                // salesOrder harus loaded
                if ($salesOrderDetail?->relationLoaded('salesOrder')) {

                    // Sudah aman → tidak akan null
                    $salesOrder = $salesOrderDetail->salesOrder->setAppends([]);
                    $data['sales_order'] = $salesOrder;

                    // ==============================================
                    // Cek deliveryOrderDetail → deliveryOrder
                    // ==============================================

                    if (
                        $salesOrderDetail->relationLoaded('deliveryOrderDetail') &&
                        $salesOrderDetail->deliveryOrderDetail?->relationLoaded('deliveryOrder')
                    ) {
                        $data['delivery_order'] = $salesOrderDetail
                            ->deliveryOrderDetail
                            ->deliveryOrder
                            ->setAppends([]);
                    }
                }
            }

            // Ini bagian asli lu → tidak gue hapus
            unset($this->salesOrderItem);
        }

        return array_merge(parent::toArray($request), $data);
    }
}
