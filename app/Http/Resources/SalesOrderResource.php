<?php

namespace App\Http\Resources;

use App\Models\SalesOrderDetail;
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
            [
                'user' => new UserResource($this->user),
                'reseller' => new UserResource($this->reseller),
                'details' => SalesOrderDetailResource::collection($this->whenLoaded('details'))
            ],
            parent::toArray($request)
        );
    }
}
