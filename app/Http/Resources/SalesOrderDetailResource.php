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
        return [
            'id' => $this->id,
            'sales_order_id' => new SalesOrderResource($this->salesOrder),
            'product_unit_id' => new ProductUnitResource($this->productUnit),
            'qty' => $this->qty,
            'real_qty' => $this->real_qty,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
