<?php

namespace App\Http\Resources\Stocks;

use App\Http\Resources\ReceiveOrderDetailResource;
use App\Http\Resources\StockProductUnitResource as ResourcesStockProductUnitResource;
use Illuminate\Http\Resources\Json\JsonResource;

class StockProductUnitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $data = [
            'childs_count' => $this->whenCounted('childs'),
            'receive_order_detail' =>  new ReceiveOrderDetailResource($this->whenLoaded('receiveOrderDetail')),
            'stock_product_unit' =>  new ResourcesStockProductUnitResource($this->whenLoaded('stockProductUnit')->load(['productUnit', 'warehouse'])),
        ];

        $salesOrder = null;
        if (
            $this->relationLoaded('salesOrderItem')
            && $this->salesOrderItem->relationLoaded('salesOrderDetail')
            && $this->salesOrderItem->salesOrderDetail->relationLoaded('salesOrder')
        ) {
            $salesOrder = $this->salesOrderItem->salesOrderDetail->salesOrder->setAppends([]);

            $data['sales_order'] = $salesOrder;

            // Check nested eager load deliveryOrder
            if (
                $this->salesOrderItem->salesOrderDetail->relationLoaded('deliveryOrderDetail') &&
                $this->salesOrderItem->salesOrderDetail->deliveryOrderDetail->relationLoaded('deliveryOrder')
            ) {
                $data['delivery_order'] = $this->salesOrderItem
                    ->salesOrderDetail
                    ->deliveryOrderDetail
                    ->deliveryOrder
                    ->setAppends([]);
            }

            unset($this->salesOrderItem);
        }

        return array_merge(parent::toArray($request), $data);
    }
}
