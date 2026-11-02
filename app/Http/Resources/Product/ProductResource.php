<?php

namespace App\Http\Resources\Product;

use App\Http\Resources\ProductBrand\ProductBrandResource;
use App\Http\Resources\ProductCategory\ProductCategoryResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'company' => $this->company,
            'name' => $this->name,
            'article_url' => $this->article_url,
            // 'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'product_category' => new ProductCategoryResource($this->whenLoaded('productCategory')),
            'product_brand' => new ProductBrandResource($this->whenLoaded('productBrand')),
        ];
    }
}
