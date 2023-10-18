<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderDetailResource extends JsonResource
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
                'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
                'sales_order' => new SalesOrderResource($this->whenLoaded('salesOrder')),
                'packaging' => new SalesOrderResource($this->whenLoaded('packaging')),
                'product_unit' => new ProductUnitResource($this->productUnit->load('packaging')),
            ]
        );
    }
}
