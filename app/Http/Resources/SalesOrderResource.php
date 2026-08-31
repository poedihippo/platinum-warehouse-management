<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
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
                'cashier_name' => $this->cashier_name,
                'total_roll_dice' => floor($this->price / config('app.min_price_roll_dice', 500000)),
                'has_delivery_order' => $this->has_delivery_order,
                'details_count' => $this->whenCounted('details'),
                'auto_discount_details' => $this->auto_discount_details,
                'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
                'user' => new UserResource($this->whenLoaded('user')),
                'reseller' => new UserResource($this->reseller),
                'details' => SalesOrderDetailResource::collection($this->whenLoaded('details')),
            ]
        );
    }
}
