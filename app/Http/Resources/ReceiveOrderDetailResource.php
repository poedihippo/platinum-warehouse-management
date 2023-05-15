<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReceiveOrderDetailResource extends JsonResource
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
            'receive_order' => new ReceiveOrderResource($this->receiveOrder),
            'product_unit' => new ProductUnitResource($this->productUnit),
            'qty' => $this->qty,
            'item_unit' => $this->item_unit,
            'bruto_unit_price' => $this->bruto_unit_price,
            'adjust_qty' => $this->adjust_qty,
            'uom_id' => new UomResource($this->uom),
            'is_package' => $this->is_package,
            'is_verified' => $this->is_verified,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
