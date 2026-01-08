<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductUnitResource extends JsonResource
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
            'id' => $this->id,
            'refer_id' => $this->refer_id,
            'refer_qty' => $this->refer_qty,
            'name' => $this->name,
            'price' => $this->price,
            // 'description' => $this->description,
            'code' => $this->code,
            'is_generate_qr' => $this->is_generate_qr,
            // 'is_auto_tempel' => $this->is_auto_tempel,
            'is_ppn' => $this->is_ppn,
            'is_auto_stock' => $this->is_auto_stock,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'uom' => new UomResource($this->uom),
            'product' => new ProductResource($this->product),
            'relations' => DefaultResource::collection($this->whenLoaded('relations')),
            'refer' => $this->refer,
            // 'packaging' =>  new self($this->whenLoaded('packaging')),
        ];
    }
}
