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
                'has_delivery_order' => $this->has_delivery_order,
                'details_count' => $this->whenCounted('details'),
                'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
                'user' => new UserResource($this->whenLoaded('user')),
                'reseller' => new UserResource($this->reseller),
                'details' => SalesOrderDetailResource::collection($this->whenLoaded('details'))
            ]
        );
    }
}
