<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductUnitStockResourceBackup extends JsonResource
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
            'qty' => $this->qty,
            'product_unit' => new ProductUnitResource($this->productUnit),
            'warehouse' => new WarehouseResource($this->warehouse),
        ];
    }
}
