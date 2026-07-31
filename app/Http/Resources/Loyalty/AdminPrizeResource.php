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
            'product_url' => $this->product_url,
            'is_active' => (bool) $this->is_active,
            // Unlike the customer-facing PrizeResource, this shows the raw
            // category (even inactive) so admins can see and reassign it.
            // Still null when the category was soft-deleted, since the
            // belongsTo relation excludes trashed rows by default.
            'category' => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name, 'is_active' => (bool) $this->category->is_active]
                : null,
            'redemptions_count' => $this->whenCounted('redemptions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
