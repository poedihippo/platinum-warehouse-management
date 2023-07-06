<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryOrderDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return array_merge(
            parent::toArray($request),
            [
                'sales_order_detail' => new SalesOrderDetailResource($this->whenLoaded('salesOrderDetail')),
                'delivery_order' => new DeliveryOrderResource($this->whenLoaded('deliveryOrder')),
            ]
        );
    }
}
