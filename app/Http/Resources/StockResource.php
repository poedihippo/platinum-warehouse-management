<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
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
            'product_unit_id' => $this->product_unit_id,
            'warehouse_id' => $this->warehouse_id,
            'receive_order_detail_id' => $this->receive_order_detail_id,
            'description' => $this->description,
            'qr_code' => $this->qr_code,
            'scanned_by' => $this->scanned_by,
            'scanned_datetime' => $this->scanned_datetime,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'product_unit' =>  new ProductUnitResource($this->whenLoaded('productUnit')),
            'warehouse' =>  new WarehouseResource($this->whenLoaded('warehouse')),
            'receive_order_detail' =>  new ReceiveOrderDetailResource($this->whenLoaded('receiveOrderDetail')),
        ];
    }
}
