<?php

namespace App\Http\Resources;

use App\Http\Resources\Stocks\BaseStockResource;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderItemResource extends JsonResource
{
    public function toArray($request)
    {
        return array_merge(
            parent::toArray($request),
            [
                'stock' => new BaseStockResource($this->whenLoaded('stock')),
                'sales_order_detail' => new SalesOrderDetailResource($this->whenLoaded('salesOrderDetail')),
                // 'stock' => new BaseStockResource($this->stock),
                // 'sales_order_detail' => new SalesOrderDetailResource($this->salesOrderDetail),
            ]
        );
    }
}
