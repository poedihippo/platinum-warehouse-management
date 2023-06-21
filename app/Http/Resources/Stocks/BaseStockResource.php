<?php

namespace App\Http\Resources\Stocks;

use App\Http\Resources\ReceiveOrderDetailResource;
use Illuminate\Http\Resources\Json\JsonResource;

class BaseStockResource extends JsonResource
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
        ];
    }
}
