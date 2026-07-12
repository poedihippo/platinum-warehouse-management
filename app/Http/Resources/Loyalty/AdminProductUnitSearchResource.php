<?php

namespace App\Http\Resources\Loyalty;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin claim line-item autocomplete row: a product unit an admin can
 * recognise (product name + unit name), with the points value the claim
 * qty will be multiplied against.
 */
class AdminProductUnitSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => trim(($this->product?->name ? $this->product->name . ' - ' : '') . $this->name),
            'code' => $this->code,
            'points_per_unit' => (int) $this->points_per_unit,
        ];
    }
}
