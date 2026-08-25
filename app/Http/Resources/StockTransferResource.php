<?php

namespace App\Http\Resources;

use App\Http\Resources\Stocks\StockProductUnitResource;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            ...parent::toArray($request),
            'stock' => new StockProductUnitResource($this->whenLoaded('stock')),
            'from_stock_product_unit' => new StockProductUnitResource($this->whenLoaded('fromStockProductUnit')?->load(['productUnit', 'warehouse'])),
            'to_stock_product_unit' => new StockProductUnitResource($this->whenLoaded('toStockProductUnit')?->load(['productUnit', 'warehouse'])),
            'from_warehouse' => new WarehouseResource($this->whenLoaded('fromWarehouse')),
            'to_warehouse' => new WarehouseResource($this->whenLoaded('toWarehouse')),
            'product_unit' => new ProductUnitResource($this->whenLoaded('productUnit')),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
