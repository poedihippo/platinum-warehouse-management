<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryOrderDetailResource extends JsonResource
{
    public function toArray($request)
    {
        return array_merge(
            parent::toArray($request),
            [
                'sales_order_detail' => new SalesOrderDetailResource($this->whenLoaded('salesOrderDetail')),
                'delivery_order' => new DeliveryOrderResource($this->whenLoaded('deliveryOrder')),
                'sales_order_items' => SalesOrderItemResource::collection($this->whenLoaded('salesOrderItems')),
                'total_verified_stock' => $this->when(
                    isset($this->total_verified_stock) || $this->relationLoaded('salesOrderItems'),
                    fn () => $this->total_verified_stock ?? $this->salesOrderItems
                        ->where('is_parent', false)
                        ->where('is_returned', false)
                        ->count()
                ),
            ]
        );
    }
}
