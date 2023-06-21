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
            ...parent::toArray($request),
            'stock_product_unit' => new StockProductUnitResource($this->whenLoaded('stockProductUnit')?->load(['productUnit', 'warehouse'])),
            'user' =>  new UserResource($this->whenLoaded('user')),
        ];
    }
}
