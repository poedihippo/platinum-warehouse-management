<?php

namespace App\Http\Resources\SalesOrder;

use App\Http\Resources\ProductUnit\ProductUnitResource;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\SalesOrder\SalesOrderResource;
use App\Http\Resources\DefaultResource;
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
                'warehouse' => new DefaultResource($this->whenLoaded('warehouse')),
                'sales_order' => new SalesOrderResource($this->whenLoaded('salesOrder')),
                'product_unit' => new ProductUnitResource($this->whenLoaded('productUnit')),
            ]
        );
    }
}
