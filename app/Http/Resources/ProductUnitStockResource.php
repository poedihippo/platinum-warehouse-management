<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductUnitStockResource extends JsonResource
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
            'name' => $this->name,
            // 'description' => $this->description,
            'code' => $this->code,
            'qty' => $this->qty,
            'warehouse' => $this->warehouse,
            'warehouse_id' => $this->warehouse_id,
            'uom' => new UomResource($this->uom),
            'product' => new ProductResource($this->product),
        ];
    }
}
