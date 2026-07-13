<?php

namespace App\Http\Resources\Loyalty;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin points-management row: a product unit an admin can recognise
 * (product name + unit name), its UOM, its current points value, and
 * whether it's curated as loyalty-eligible (so the frontend can flag a
 * stray unit that has points despite not being eligible). Same display
 * convention as AdminProductUnitSearchResource.
 */
class AdminProductUnitPointsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => trim(($this->product?->name ? $this->product->name . ' - ' : '') . $this->name),
            'code' => $this->code,
            'uom' => $this->uom?->name,
            'points_per_unit' => (int) $this->points_per_unit,
            'loyalty_eligible' => (bool) $this->loyalty_eligible,
        ];
    }
}
