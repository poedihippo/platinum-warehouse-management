<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdjustmentRequestResource extends JsonResource
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
            'stock_product_unit_id' => $this->stock_product_unit_id,
            'value' => $this->value,
            'is_increment' => $this->is_increment,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // 'stock_product_unit' => new StockProductUnitResource($this->whenLoaded('stockProductUnit')),
            'stock_product_unit' => new StockProductUnitResource($this->whenLoaded('stockProductUnit')?->load(['productUnit', 'warehouse'])),
            'user' =>  new UserResource($this->whenLoaded('user')),
        ];
    }
}
