<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReceiveOrderResource extends JsonResource
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
                'details' => ReceiveOrderDetailResource::collection($this->whenLoaded('details')),
                'supplier' => new SupplierResource($this->supplier),
                'warehouse' => new WarehouseResource($this->warehouse)
            ]
        );
    }
}
