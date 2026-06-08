<?php

namespace App\Http\Resources\Loyalty;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin/bejo view of a prize. Includes timestamps and (when counted)
 * the number of redemptions referencing this prize.
 */
class AdminPrizeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'points_cost' => (int) $this->points_cost,
            'stock' => (int) $this->stock,
            'photo_url' => $this->photo_url,
            'is_active' => (bool) $this->is_active,
            'redemptions_count' => $this->whenCounted('redemptions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
