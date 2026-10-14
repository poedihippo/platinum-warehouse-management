<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryOrderResource extends JsonResource
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
                'is_marketplace_source' => ! is_null($this->sales_order_id),
                // 'sales_order' => new SalesOrderResource($this->whenLoaded('salesOrder')->loadCount('details')),
                'user' => new UserResource($this->whenLoaded('user')),
                'reseller' => new UserResource($this->reseller),
            ]
        );
    }
}
