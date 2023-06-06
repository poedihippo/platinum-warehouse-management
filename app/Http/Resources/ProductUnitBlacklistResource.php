<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductUnitBlacklistResource extends JsonResource
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
            'product_unit_id' => $this->product_unit_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'product_unit' => new ProductUnitResource($this->productUnit),
        ];
    }
}
