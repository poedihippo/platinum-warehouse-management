<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'parent_id' => $this->parent_id,
            'product_unit_id' => $this->product_unit_id,
            'warehouse_id' => $this->warehouse_id,
            'receive_order_id' => $this->receive_order_id,
            'receive_order_detail_id' => $this->receive_order_detail_id,
            'description' => $this->description,
            'qr_code' => Storage::disk('s3')->temporaryUrl($this->qr_code, now()->addMinutes(5)),
            'scanned_by' => $this->scanned_by,
            'scanned_datetime' => $this->scanned_datetime,
            'stocks_count' => $this->whenCounted('childs'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'product_unit' =>  new ProductUnitResource($this->whenLoaded('productUnit')),
            'warehouse' =>  new WarehouseResource($this->whenLoaded('warehouse')),
            'receive_order_detail' =>  new ReceiveOrderDetailResource($this->whenLoaded('receiveOrderDetail')),
        ];
    }
}
