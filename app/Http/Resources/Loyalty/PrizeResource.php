<?php

namespace App\Http\Resources\Loyalty;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing prize representation (catalog + detail).
 */
class PrizeResource extends JsonResource
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
            // Null when the prize has no category, or when its category is
            // inactive/deleted — inactive/deleted categories are invisible
            // to customers, so those prizes read as uncategorized here.
            'category' => $this->category && $this->category->is_active
                ? ['id' => $this->category->id, 'name' => $this->category->name]
                : null,
        ];
    }
}
