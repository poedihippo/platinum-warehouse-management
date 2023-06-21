<?php

namespace App\Http\Resources\Stocks;

use App\Http\Resources\ReceiveOrderDetailResource;
use App\Http\Resources\StockProductUnitResource as ResourcesStockProductUnitResource;
use Illuminate\Http\Resources\Json\JsonResource;

class StockProductUnitResource extends JsonResource
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
            'childs_count' => $this->whenCounted('childs'),
            'receive_order_detail' =>  new ReceiveOrderDetailResource($this->whenLoaded('receiveOrderDetail')),
            'stock_product_unit' =>  new ResourcesStockProductUnitResource($this->whenLoaded('stockProductUnit')->load(['productUnit', 'warehouse'])),
        ];
    }
}
