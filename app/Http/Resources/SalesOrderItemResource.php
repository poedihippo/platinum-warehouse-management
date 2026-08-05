<?php

namespace App\Http\Resources;

use App\Http\Resources\Stocks\BaseStockResource;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return array_merge(
            parent::toArray($request),
            [
                'stock' => new BaseStockResource($this->stock),
                'sales_order_detail' => new SalesOrderDetailResource($this->salesOrderDetail),
            ]
        );
    }
}
