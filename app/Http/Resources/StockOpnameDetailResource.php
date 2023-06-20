<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockOpnameDetailResource extends JsonResource
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
            'stock_opname_id' => $this->stock_opname_id,
            // 'stock_product_unit_id' => $this->stock_product_unit_id,
            // 'qty' => $this->qty,
            'qty' => $this->whenCounted('stockOpnameItems'),
            'adjust_qty' => $this->total_adjust_qty ?? 0,
            // 'adjust_qty' => $this->adjust_qty,
            'is_done' => $this->is_done,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'stock_product_unit' =>  new StockProductUnitResource($this->whenLoaded('stockProductUnit')),
            'stock_opname' =>  new StockOpnameResource($this->whenLoaded('stockOpname')),
        ];
    }
}
